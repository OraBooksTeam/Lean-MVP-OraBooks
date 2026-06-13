<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Dashboard</title>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php wp_head(); ?>
    <style>
        /* Forceful CSS to match the OraBooks blue/green brand scheme and prevent external overrides */
        :root {
            --inv-sidebar-bg: #1569B3 !important;
            --inv-sidebar-brand-bg: linear-gradient(to bottom, #0f528e, #1569B3) !important;
            --inv-nav-text: #d8ecfb !important;
            --inv-nav-hover-bg: rgba(57, 181, 74, 0.16) !important;
            --inv-nav-active-bg: linear-gradient(90deg, rgba(57, 181, 74, 0.28) 0%, rgba(21, 105, 179, 0.5) 100%) !important;
            --inv-nav-active-text: #ffffff !important;
            --inv-nav-active-border: rgba(57, 181, 74, 0.45) !important;
            --inv-submenu-bg: rgba(13, 79, 138, 0.45) !important;
            --inv-subnav-active-bg: rgba(57, 181, 74, 0.32) !important;
        }
        body { margin: 0; padding: 0; height: 100vh; display: flex; flex-direction: column; overflow: hidden; background-color: #f1f5f9 !important; font-family: 'Inter', sans-serif !important; }
        
        /* Main Layout Component */
        :root {
            --taxora-topbar-height: 56px;
        }

        #inventory-main-layout {
            display: flex !important;
            flex: 1 1 auto !important;
            min-height: 0 !important;
            height: calc(100vh - 64px) !important;
            width: 100% !important;
            overflow: hidden !important;
        }

        /* TaxOra fixed topbar clearance (class added via body_class + topbar.js) */
        body.taxora-topbar-active.taxora-topbar-frontend #inventory-main-layout,
        body.has-taxora-topbar #inventory-main-layout {
            height: calc(100vh - 64px - var(--taxora-topbar-height)) !important;
            max-height: calc(100vh - 64px - var(--taxora-topbar-height)) !important;
        }

        body.taxora-topbar-active.taxora-topbar-frontend #inventory-main-layout > main,
        body.has-taxora-topbar #inventory-main-layout > main {
            padding-top: 1.25rem !important;
        }

        @media (max-width: 1024px) {
            body.taxora-topbar-active.taxora-topbar-frontend #inventory-mobile-header,
            body.has-taxora-topbar #inventory-mobile-header {
                margin-top: var(--taxora-topbar-height) !important;
            }

            #inventory-main-layout {
                height: calc(100vh - 64px) !important;
            }

            body.taxora-topbar-active.taxora-topbar-frontend #inventory-main-layout,
            body.has-taxora-topbar #inventory-main-layout {
                height: calc(100vh - 64px - var(--taxora-topbar-height)) !important;
                max-height: calc(100vh - 64px - var(--taxora-topbar-height)) !important;
            }
        }

        @media (max-width: 640px) {
            :root {
                --taxora-topbar-height: 52px;
            }

            body.taxora-topbar-active.taxora-topbar-frontend #inventory-mobile-header,
            body.has-taxora-topbar #inventory-mobile-header {
                margin-top: var(--taxora-topbar-height) !important;
            }

            body.taxora-topbar-active.taxora-topbar-frontend #inventory-main-layout,
            body.has-taxora-topbar #inventory-main-layout {
                height: calc(100vh - 64px - var(--taxora-topbar-height)) !important;
                max-height: calc(100vh - 64px - var(--taxora-topbar-height)) !important;
            }
        }

        @media (max-width: 480px) {
            :root {
                --taxora-topbar-height: 50px;
            }

            body.taxora-topbar-active.taxora-topbar-frontend #inventory-mobile-header,
            body.has-taxora-topbar #inventory-mobile-header {
                margin-top: var(--taxora-topbar-height) !important;
            }

            body.taxora-topbar-active.taxora-topbar-frontend #inventory-main-layout,
            body.has-taxora-topbar #inventory-main-layout {
                height: calc(100vh - 64px - var(--taxora-topbar-height)) !important;
                max-height: calc(100vh - 64px - var(--taxora-topbar-height)) !important;
            }
        }

		@media (min-width: 1025px) {
			#inventory-main-layout {
				flex: 1 1 auto !important;
				min-height: 0 !important;
				height: 100vh !important;
				max-height: 100vh !important;
			}

			body.taxora-topbar-active.taxora-topbar-frontend #inventory-main-layout,
			body.has-taxora-topbar #inventory-main-layout {
				margin-top: var(--taxora-topbar-height) !important;
				height: calc(100vh - var(--taxora-topbar-height)) !important;
				max-height: calc(100vh - var(--taxora-topbar-height)) !important;
			}

			body.taxora-topbar-active.taxora-topbar-frontend #inventory-sidebar.inventory-sidebar,
			body.has-taxora-topbar #inventory-sidebar.inventory-sidebar {
				max-height: calc(100vh - var(--taxora-topbar-height)) !important;
			}
		}
        
        /* Sidebar Styling - Forced */
        #inventory-sidebar.inventory-sidebar {
            background-color: var(--inv-sidebar-bg) !important;
            color: #f8fafc !important;
            width: 280px !important;
            min-width: 280px !important;
            border-right: 1px solid #1e293b !important;
            z-index: 50 !important;
            display: flex !important;
            flex-direction: column !important;
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1) !important;
            transition: all 0.3s ease !important;
        }

        .inventory-sidebar ul {
            list-style: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .inventory-sidebar li {
            list-style: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .inventory-sidebar-brand {
            padding: 1.5rem 2rem !important;
            background: var(--inv-sidebar-brand-bg) !important;
            border-bottom: 1px solid #1e293b !important;
        }

        .inventory-sidebar-brand h3 {
            margin: 0 !important;
            font-size: 1.25rem !important;
            font-weight: 800 !important;
            background: linear-gradient(135deg, #fff 0%, #94a3b8 100%) !important;
            -webkit-background-clip: text !important;
            background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            letter-spacing: -0.5px !important;
        }

        /* Nav Links & Toggles Styling */
        .inventory-menu-toggle {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            width: 100% !important;
        }

        .inv-nav-link {
            display: flex !important;
            align-items: center !important;
            padding: 0.75rem 1rem !important;
            color: var(--inv-nav-text) !important;
            text-decoration: none !important;
            border-radius: 0.75rem !important;
            font-weight: 500 !important;
            transition: all 0.2s !important;
            border: 1px solid transparent !important;
            margin-bottom: 0.25rem !important;
        }

        .inv-nav-link:hover {
            background-color: var(--inv-nav-hover-bg) !important;
            color: #fff !important;
        }

        .inv-nav-link.active {
            background: var(--inv-nav-active-bg) !important;
            color: var(--inv-nav-active-text) !important;
            border: 1px solid var(--inv-nav-active-border) !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;
        }

        /* Submenu Styling */
        .inv-submenu {
            transition: max-height 0.3s ease-in-out !important;
            max-height: 0;
            overflow: hidden !important;
            background-color: var(--inv-submenu-bg) !important;
            border-radius: 0.75rem !important;
            margin-left: 0.5rem !important;
            margin-right: 0.5rem !important;
        }
        .inv-submenu.open {
            max-height: 1000px;
            margin-bottom: 0.5rem !important;
            padding: 0.25rem 0 !important;
        }

        .inv-subnav-link {
            display: flex !important;
            align-items: center !important;
            padding: 0.5rem 1rem !important;
            color: #cbd5e1 !important;
            text-decoration: none !important;
            font-size: 0.85rem !important;
            border-radius: 0.5rem !important;
            transition: all 0.2s !important;
        }

        .inv-subnav-link i {
            font-size: 0.8rem !important;
            width: 1.25rem !important;
            text-align: center !important;
            margin-right: 0.75rem !important;
            opacity: 0.7 !important;
        }

        .inv-subnav-link:hover i,
        .inv-subnav-link.active i {
            opacity: 1 !important;
        }

        .inv-subnav-link:hover {
            color: #fff !important;
            background: rgba(255, 255, 255, 0.03) !important;
        }

        .inv-subnav-link.active {
            color: #fff !important;
            background: var(--inv-subnav-active-bg) !important;
            font-weight: 600 !important;
        }

        /* Sidebar Overlay */
        #sidebar-overlay {
            display: none;
            position: fixed;
            top: 56px !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 40;
            backdrop-filter: blur(2px);
        }
        #sidebar-overlay.active {
            display: block !important;
        }

        @media (max-width: 640px) {
            #sidebar-overlay {
                top: 52px !important;
            }
        }

        @media (max-width: 480px) {
            #sidebar-overlay {
                top: 50px !important;
            }
        }

        /* Utility */
        .rotate-180 { transform: rotate(180deg) !important; }
        .no-scrollbar::-webkit-scrollbar { display: none !important; }
        .no-scrollbar { -ms-overflow-style: none !important; scrollbar-width: none !important; }
        
        .menu-arrow {
            font-size: 0.75rem !important;
            transition: transform 0.3s ease !important;
            opacity: 0.7;
        }
        .inventory-menu-toggle[aria-expanded="true"] .menu-arrow {
            transform: rotate(180deg) !important;
        }
        .inv-nav-link:hover .menu-arrow, .inventory-menu-toggle:hover .menu-arrow {
            opacity: 1;
        }
        
        /* Mobile Header */
        #inventory-mobile-header {
            display: flex !important;
            height: 64px !important;
            align-items: center !important;
            justify-content: space-between !important;
            padding: 0 1rem !important;
            background-color: #0f172a !important;
            color: white !important;
            border-bottom: 1px solid #1e293b !important;
        }

        @media (min-width: 1025px) {
            #inventory-mobile-header {
                display: none !important;
            }
        }

        /* Sidebar Styling - Forced */
        #inventory-sidebar.inventory-sidebar {
            background-color: var(--inv-sidebar-bg) !important;
            color: #f8fafc !important;
            width: 280px !important;
            min-width: 280px !important;
            max-width: 280px !important;
            border-right: 1px solid #1e293b !important;
            z-index: 50 !important;
            display: flex !important;
            flex-direction: column !important;
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1) !important;
            transition: transform 0.3s ease, left 0.3s ease !important;
        }

        @media (max-width: 1024px) {
            #inventory-sidebar.inventory-sidebar {
                position: fixed !important;
                top: 56px !important;
                left: -280px !important;
                height: calc(100vh - 56px) !important;
                z-index: 100 !important;
            }
            #inventory-sidebar.inventory-sidebar.mobile-open {
                left: 0 !important;
            }

            @media (max-width: 640px) {
                #inventory-sidebar.inventory-sidebar {
                    top: 52px !important;
                    height: calc(100vh - 52px) !important;
                }
            }

            @media (max-width: 480px) {
                #inventory-sidebar.inventory-sidebar {
                    top: 50px !important;
                    height: calc(100vh - 50px) !important;
                }
            }
        }
    </style>

</head>
<body <?php body_class( 'bg-gray-100 font-sans h-screen flex flex-col overflow-hidden orabooks-inventory' ); ?>>
    <?php wp_body_open(); ?>
    <!-- Mobile Header -->
    <header id="inventory-mobile-header" class="lg:hidden">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <span style="font-size: 1.25rem; font-weight: 800; color: #ffffff;">ORA INVENTORY</span>
        </div>
        <button id="mobile-sidebar-toggle" style="background: none; border: none; color: #94a3b8; cursor: pointer;">
            <i class="fa-solid fa-bars" style="font-size: 1.5rem;"></i>
        </button>
    </header>

    <!-- Sidebar Overlay -->
    <div id="sidebar-overlay"></div>
    
    <!-- Main Layout -->
    <div class="flex flex-1 overflow-hidden" id="inventory-main-layout">
        <?php $current_view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'dashboard'; ?>
        <!-- Sidebar -->
        <aside class="inventory-sidebar bg-slate-900 text-white flex-shrink-0 overflow-y-auto no-scrollbar" id="inventory-sidebar" style="display: flex !important; width: 280px !important;">
            <div class="inventory-sidebar-brand">
                <h3>ORA INVENTORY</h3>
            </div>
            <div class="p-4" style="padding: 1rem !important;">
                <!-- <div class="uppercase text-xs font-semibold text-gray-400 mb-2 tracking-wider">Menu</div> -->
                <nav>
                    <ul class="space-y-1">
                        <?php echo Frontend_Inventory_Sidebar::render_sidebar($current_view); ?>
                        <?php if (false): ?>
                        <!-- Dashboard Home -->
                        <li>
                            <a href="<?php echo esc_url( add_query_arg( 'view', 'dashboard' ) ); ?>" class="inv-nav-link flex items-center px-4 py-3 <?php echo ($current_view === 'dashboard') ? 'active' : ''; ?> rounded-md text-white hover:bg-gray-700 transition-colors">
                                <i class="fa-solid fa-gauge w-5 text-center mr-2"></i>
                                <span class="font-medium">Dashboard</span>
                            </a>
                        </li>

                        <!-- All Features -->
                        <li>
                            <a href="<?php echo esc_url( add_query_arg( 'view', 'all-features' ) ); ?>" class="inv-nav-link flex items-center px-4 py-3 <?php echo ($current_view === 'all-features') ? 'active' : ''; ?> rounded-md text-white hover:bg-gray-700 transition-colors">
                                <i class="fa-solid fa-star w-5 text-center mr-2"></i>
                                <span class="font-medium">All Features</span>
                            </a>
                        </li>

                        <!-- Settings -->
                        <li>
                            <?php $settings_active = in_array($current_view, ['store-profile', 'warehouse', 'db-backup']); ?>
                            <button class="w-full flex items-center justify-between px-3 py-3 text-white hover:bg-gray-700 hover:text-white rounded-md transition-colors focus:outline-none inventory-menu-toggle <?php echo $settings_active ? 'active' : ''; ?>" <?php echo $settings_active ? 'aria-expanded="true"' : 'aria-expanded="false"'; ?>>
                                <span class="flex items-center">
                                    <i class="fa-solid fa-gear w-5 text-center mr-2"></i>
                                    <span class="font-medium">Settings</span>
                                </span>
                                <i class="fa-solid fa-chevron-down menu-arrow"></i>
                            </button>
                            <ul class="inv-submenu submenu bg-gray-900 rounded-md mt-1 space-y-1 pl-8 pr-2 pb-2 <?php echo $settings_active ? 'open' : ''; ?>" style="<?php echo $settings_active ? 'max-height: 1000px;' : ''; ?>">
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'store-profile' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'store-profile') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-store"></i> Store Profile
                                </a></li>
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'warehouse' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'warehouse') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-warehouse"></i> Warehouse
                                </a></li>
                                
                                <!-- <li><a href="<?php //echo esc_url( add_query_arg( 'view', 'db-backup' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php //echo ($current_view === 'db-backup') ? 'active text-white font-medium' : ''; ?>">DB Backup</a></li> -->
                            </ul>
                        </li>

                        <!-- Items -->
                        <li>
                            <?php $items_active = in_array($current_view, ['view-items', 'add-item', 'add-service', 'categories', 'brands', 'units', 'variants-list', 'print-labels']); ?>
                            <button class="w-full flex items-center justify-between px-3 py-3 text-white hover:bg-gray-700 hover:text-white rounded-md transition-colors focus:outline-none inventory-menu-toggle <?php echo $items_active ? 'active' : ''; ?>" <?php echo $items_active ? 'aria-expanded="true"' : 'aria-expanded="false"'; ?>>
                                <span class="flex items-center">
                                    <i class="fa-solid fa-sitemap w-5 text-center mr-2"></i>
                                    <span class="font-medium">Items</span>
                                </span>
                                <i class="fa-solid fa-chevron-down menu-arrow"></i>
                            </button>
                            <ul class="inv-submenu submenu bg-gray-900 rounded-md mt-1 space-y-1 pl-8 pr-2 pb-2 <?php echo $items_active ? 'open' : ''; ?>" style="<?php echo $items_active ? 'max-height: 1000px;' : ''; ?>">
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'view-items' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'view-items') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-list"></i> View Items
                                </a></li>
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'add-item' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'add-item') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-plus"></i> Add Item
                                </a></li>
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'import-items' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'import-items') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-file-import"></i> Import Items
                                </a></li>
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'add-service' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'add-service') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-concierge-bell"></i> Add Service
                                </a></li>
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'categories' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'categories') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-tags"></i> Categories
                                </a></li>
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'brands' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'brands') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-copyright"></i> Brands
                                </a></li>
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'units' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'units') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-ruler"></i> Units
                                </a></li>
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'variants-list' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'variants-list') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-layer-group"></i> Variants List
                                </a></li>
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'print-labels' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'print-labels') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-print"></i> Print Label
                                </a></li>
                            </ul>
                        </li>

                        <!-- Contact -->
                        <li>
                            <?php $contact_active = in_array($current_view, ['customers', 'suppliers']); ?>
                            <button class="w-full flex items-center justify-between px-3 py-3 text-white hover:bg-gray-700 hover:text-white rounded-md transition-colors focus:outline-none inventory-menu-toggle <?php echo $contact_active ? 'active' : ''; ?>" <?php echo $contact_active ? 'aria-expanded="true"' : 'aria-expanded="false"'; ?>>
                                <span class="flex items-center">
                                    <i class="fa-solid fa-address-book w-5 text-center mr-2"></i>
                                    <span class="font-medium">Contact</span>
                                </span>
                                <i class="fa-solid fa-chevron-down menu-arrow"></i>
                            </button>
                            <ul class="inv-submenu submenu bg-gray-900 rounded-md mt-1 space-y-1 pl-8 pr-2 pb-2 <?php echo $contact_active ? 'open' : ''; ?>" style="<?php echo $contact_active ? 'max-height: 1000px;' : ''; ?>">
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'customers' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'customers') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-user-group"></i> Customers
                                </a></li>
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'import-customers' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'import-customers') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-file-import"></i> Import Customers
                                </a></li>
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'suppliers' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'suppliers') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-truck-field"></i> Suppliers
                                </a></li>
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'import-suppliers' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'import-suppliers') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-file-import"></i> Import Suppliers
                                </a></li>
                            </ul>
                        </li>

                         <!-- Sales -->
                         <li>
                            <?php $sales_active = in_array($current_view, ['view-sales', 'add-sale', 'pos-sale']); ?>
                            <button class="w-full flex items-center justify-between px-3 py-3 text-white hover:bg-gray-700 hover:text-white rounded-md transition-colors focus:outline-none inventory-menu-toggle <?php echo $sales_active ? 'active' : ''; ?>" <?php echo $sales_active ? 'aria-expanded="true"' : 'aria-expanded="false"'; ?>>
                                <span class="flex items-center">
                                    <i class="fa-solid fa-cart-shopping w-5 text-center mr-2"></i>
                                    <span class="font-medium">Sales</span>
                                </span>
                                <i class="fa-solid fa-chevron-down menu-arrow"></i>
                            </button>
                            <ul class="inv-submenu submenu bg-gray-900 rounded-md mt-1 space-y-1 pl-8 pr-2 pb-2 <?php echo $sales_active ? 'open' : ''; ?>" style="<?php echo $sales_active ? 'max-height: 1000px;' : ''; ?>">
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'view-sales' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'view-sales') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-file-invoice"></i> View Sales
                                </a></li>
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'add-sale' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'add-sale') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-cart-plus"></i> Add Sale
                                </a></li>
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'pos-sale' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'pos-sale') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-cash-register"></i> POS Sale
                                </a></li>
                            </ul>
                        </li>

                        <!-- Purchase -->
                        <li>
                            <?php $purchase_active = in_array($current_view, ['view-purchase', 'add-purchase']); ?>
                            <button class="w-full flex items-center justify-between px-3 py-3 text-white hover:bg-gray-700 hover:text-white rounded-md transition-colors focus:outline-none inventory-menu-toggle <?php echo $purchase_active ? 'active' : ''; ?>" <?php echo $purchase_active ? 'aria-expanded="true"' : 'aria-expanded="false"'; ?>>
                                <span class="flex items-center">
                                    <i class="fa-solid fa-bag-shopping w-5 text-center mr-2"></i>
                                    <span class="font-medium">Purchase</span>
                                </span>
                                <i class="fa-solid fa-chevron-down menu-arrow"></i>
                            </button>
                            <ul class="inv-submenu submenu bg-gray-900 rounded-md mt-1 space-y-1 pl-8 pr-2 pb-2 <?php echo $purchase_active ? 'open' : ''; ?>" style="<?php echo $purchase_active ? 'max-height: 1000px;' : ''; ?>">
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'view-purchase' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'view-purchase') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-file-invoice-dollar"></i> View Purchase
                                </a></li>
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'add-purchase' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'add-purchase') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-bag-shopping"></i> Add Purchase
                                </a></li>
                            </ul>
                        </li>

                        <!-- Stock -->
                        <li>
                            <?php $stock_active = in_array($current_view, ['adjustment-list', 'transfer-list']); ?>
                            <button class="w-full flex items-center justify-between px-3 py-3 text-white hover:bg-gray-700 hover:text-white rounded-md transition-colors focus:outline-none inventory-menu-toggle <?php echo $stock_active ? 'active' : ''; ?>" <?php echo $stock_active ? 'aria-expanded="true"' : 'aria-expanded="false"'; ?>>
                                <span class="flex items-center">
                                    <i class="fa-solid fa-warehouse w-5 text-center mr-2"></i>
                                    <span class="font-medium">Stock</span>
                                </span>
                                <i class="fa-solid fa-chevron-down menu-arrow"></i>
                            </button>
                            <ul class="inv-submenu submenu bg-gray-900 rounded-md mt-1 space-y-1 pl-8 pr-2 pb-2 <?php echo $stock_active ? 'open' : ''; ?>" style="<?php echo $stock_active ? 'max-height: 1000px;' : ''; ?>">
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'adjustment-list' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'adjustment-list') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-sliders"></i> Adjustment List
                                </a></li>
                                <li><a href="<?php echo esc_url( add_query_arg( 'view', 'transfer-list' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'transfer-list') ? 'active text-white font-medium' : ''; ?>">
                                    <i class="fa-solid fa-right-left"></i> Transfer List
                                </a></li>
                            </ul>
                        </li>

                        <!-- Reports -->
                        <li>
                            <?php $reports_active = in_array($current_view, ['all-reports', 'sales-report', 'purchase-report', 'stock-report', 'profit-loss-report', 'customer-due-report', 'sales-payment-report', 'customer-payment-report', 'supplier-payment-report', 'supplier-due-report', 'sales-summary-report', 'stock-transfer-report', 'journal-report', 'trial-balance-report', 'income-statement-report', 'balance-sheet-report', 'ledger-report']); ?>
                            <button class="w-full flex items-center justify-between px-3 py-3 text-white hover:bg-gray-700 hover:text-white rounded-md transition-colors focus:outline-none inventory-menu-toggle <?php echo $reports_active ? 'active' : ''; ?>" <?php echo $reports_active ? 'aria-expanded="true"' : 'aria-expanded="false"'; ?>>
                                <span class="flex items-center">
                                    <i class="fa-solid fa-chart-line w-5 text-center mr-2"></i>
                                    <span class="font-medium">Reports</span>
                                </span>
                                <i class="fa-solid fa-chevron-down menu-arrow"></i>
                            </button>
                            <ul class="inv-submenu submenu bg-gray-900 rounded-md mt-1 space-y-1 pl-8 pr-2 pb-2 <?php echo $reports_active ? 'open' : ''; ?>" style="<?php echo $reports_active ? 'max-height: 1000px;' : ''; ?>">
                                 <li><a href="<?php echo esc_url( add_query_arg( 'view', 'all-reports' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'all-reports') ? 'active text-white font-medium' : ''; ?>">
                                     <i class="fa-solid fa-list-check"></i> All Reports
                                 </a></li>
                                 <li><a href="<?php echo esc_url( add_query_arg( 'view', 'sales-report' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'sales-report') ? 'active text-white font-medium' : ''; ?>">
                                     <i class="fa-solid fa-chart-bar"></i> Sales Report
                                 </a></li>
                                 <li><a href="<?php echo esc_url( add_query_arg( 'view', 'purchase-report' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'purchase-report') ? 'active text-white font-medium' : ''; ?>">
                                     <i class="fa-solid fa-chart-pie"></i> Purchase Report
                                 </a></li>
                                 <li><a href="<?php echo esc_url( add_query_arg( 'view', 'stock-report' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'stock-report') ? 'active text-white font-medium' : ''; ?>">
                                     <i class="fa-solid fa-boxes-stacked"></i> Stock Report
                                 </a></li>
                                 <li><a href="<?php echo esc_url( add_query_arg( 'view', 'profit-loss-report' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'profit-loss-report') ? 'active text-white font-medium' : ''; ?>">
                                     <i class="fa-solid fa-scale-balanced"></i> Profit & Loss Report
                                 </a></li>
                                 <li><a href="<?php echo esc_url( add_query_arg( 'view', 'customer-due-report' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'customer-due-report') ? 'active text-white font-medium' : ''; ?>">
                                     <i class="fa-solid fa-user-clock"></i> Customer Due Report
                                 </a></li>
                                 <li><a href="<?php echo esc_url( add_query_arg( 'view', 'sales-payment-report' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'sales-payment-report') ? 'active text-white font-medium' : ''; ?>">
                                     <i class="fa-solid fa-money-check-dollar"></i> Sales & Payment Report
                                 </a></li>
                                 <li><a href="<?php echo esc_url( add_query_arg( 'view', 'customer-payment-report' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'customer-payment-report') ? 'active text-white font-medium' : ''; ?>">
                                     <i class="fa-solid fa-hand-holding-dollar"></i> Customer Payment Report
                                 </a></li>
                                 <li><a href="<?php echo esc_url( add_query_arg( 'view', 'supplier-payment-report' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'supplier-payment-report') ? 'active text-white font-medium' : ''; ?>">
                                     <i class="fa-solid fa-file-circle-check"></i> Supplier Payment Report
                                 </a></li>
                                 <li><a href="<?php echo esc_url( add_query_arg( 'view', 'supplier-due-report' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'supplier-due-report') ? 'active text-white font-medium' : ''; ?>">
                                     <i class="fa-solid fa-truck-ramp-box"></i> Supplier Due Report
                                 </a></li>
                                 <li><a href="<?php echo esc_url( add_query_arg( 'view', 'sales-summary-report' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'sales-summary-report') ? 'active text-white font-medium' : ''; ?>">
                                     <i class="fa-solid fa-clipboard-list"></i> Sales Summary Report
                                 </a></li>
                                 <li><a href="<?php echo esc_url( add_query_arg( 'view', 'stock-transfer-report' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'stock-transfer-report') ? 'active text-white font-medium' : ''; ?>">
                                     <i class="fa-solid fa-truck-moving"></i> Stock Transfer Report
                                 </a></li>
                                 <li><a href="<?php echo esc_url( add_query_arg( 'view', 'journal-report' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'journal-report') ? 'active text-white font-medium' : ''; ?>">
                                     <i class="fa-solid fa-book"></i> Journal Report
                                 </a></li>
                                 <li><a href="<?php echo esc_url( add_query_arg( 'view', 'trial-balance-report' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'trial-balance-report') ? 'active text-white font-medium' : ''; ?>">
                                     <i class="fa-solid fa-scale-balanced"></i> Trial Balance Report
                                 </a></li>
                                 <li><a href="<?php echo esc_url( add_query_arg( 'view', 'income-statement-report' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'income-statement-report') ? 'active text-white font-medium' : ''; ?>">
                                     <i class="fa-solid fa-file-invoice-dollar"></i> Income Statement
                                 </a></li>
                                 <li><a href="<?php echo esc_url( add_query_arg( 'view', 'balance-sheet-report' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'balance-sheet-report') ? 'active text-white font-medium' : ''; ?>">
                                     <i class="fa-solid fa-scale-unbalanced"></i> Balance Sheet
                                 </a></li>
                                 <li><a href="<?php echo esc_url( add_query_arg( 'view', 'ledger-report' ) ); ?>" class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo ($current_view === 'ledger-report') ? 'active text-white font-medium' : ''; ?>">
                                     <i class="fa-solid fa-book-journal-whills"></i> Ledger Report
                                 </a></li>
                             </ul>
                         </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-50 p-6" style="flex: 1 !important; width: 100% !important;">
            <!-- Dynamic Content Loading -->
            <?php
            $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'dashboard';

            // Access Control Check
        if (!current_user_can('manage_options') && !Frontend_Inventory_Permissions::has_view_permission($view)) {
            echo '<div class="container mt-8"><div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative shadow-sm" role="alert">
                    <strong class="font-bold">Access Denied!</strong>
                    <span class="block sm:inline">You do not have permission to access the <strong>' . esc_html($view) . '</strong> module. Please contact your administrator.</span>
                  </div></div>';
            return;
        }

        switch ($view) {
                case 'store-profile':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/settings/store-profile.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/settings/store-profile.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found.</div>";
                    }
                    break;
                case 'warehouse':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/settings/warehouse.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/settings/warehouse.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/settings/warehouse.php</div>";
                    }
                    break;
                case 'module':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/settings/module.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/settings/module.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/settings/module.php</div>";
                    }
                    break;
                case 'db-backup':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/settings/db-backup.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/settings/db-backup.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/settings/db-backup.php</div>";
                    }
                    break;
                case 'user-permissions':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/settings/user-permissions.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/settings/user-permissions.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/settings/user-permissions.php</div>";
                    }
                    break;
                case 'add-features':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/settings/add-features.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/settings/add-features.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/settings/add-features.php</div>";
                    }
                    break;
                case 'view-items':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/items/view-items.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/items/view-items.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/items/view-items.php</div>";
                    }
                    break;
                case 'import-items':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/items/import-items.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/items/import-items.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/items/import-items.php</div>";
                    }
                    break;
                case 'add-item':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/items/add-item.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/items/add-item.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/items/add-item.php</div>";
                    }
                    break;
                case 'edit-item':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/items/edit-item.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/items/edit-item.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/items/edit-item.php</div>";
                    }
                    break;
                case 'add-service':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/items/add-service.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/items/add-service.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/items/add-service.php</div>";
                    }
                    break;
                case 'brands':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/settings/brands.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/settings/brands.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/settings/brands.php</div>";
                    }
                    break;
                case 'categories':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/settings/categories.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/settings/categories.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/settings/categories.php</div>";
                    }
                    break;
                case 'units':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/settings/units.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/settings/units.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/settings/units.php</div>";
                    }
                    break;
                case 'variants-list':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/items/variants-list.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/items/variants-list.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/items/variants-list.php</div>";
                    }
                    break;
                case 'print-labels':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/items/print-labels.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/items/print-labels.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/items/print-labels.php</div>";
                    }
                    break;
                case 'customers':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/contact/customers.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/contact/customers.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/contact/customers.php</div>";
                    }
                    break;
                case 'import-customers':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/contact/import-customers.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/contact/import-customers.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/contact/import-customers.php</div>";
                    }
                    break;
                case 'suppliers':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/contact/suppliers.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/contact/suppliers.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/contact/suppliers.php</div>";
                    }
                    break;
                case 'import-suppliers':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/contact/import-suppliers.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/contact/import-suppliers.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/contact/import-suppliers.php</div>";
                    }
                    break;
                case 'customer-pay':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/contact/customer-pay.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/contact/customer-pay.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/contact/customer-pay.php</div>";
                    }
                    break;
                case 'supplier-pay':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/contact/supplier-pay.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/contact/supplier-pay.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/contact/supplier-pay.php</div>";
                    }
                    break;
                
                // Employees
                case 'view-employees':
                case 'add-employee':
                case 'edit-employee':
                    if (class_exists('Frontend_Inventory_Employees')) {
                        echo Frontend_Inventory_Employees::render_employees_list();
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: Employee management class not found.</div>";
                    }
                    break;
                case 'roles':
                    if (class_exists('Frontend_Inventory_Roles')) {
                        echo Frontend_Inventory_Roles::render_roles_list();
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: Roles management class not found.</div>";
                    }
                    break;
                case 'add-sale':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/sales/add-sale.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/sales/add-sale.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/sales/add-sale.php</div>";
                    }
                    break;
                case 'view-sales':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/sales/view-sales.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/sales/view-sales.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/sales/view-sales.php</div>";
                    }
                    break;
                case 'sales-order-list':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/sales/sales-order-list.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/sales/sales-order-list.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/sales/sales-order-list.php</div>";
                    }
                    break;
                case 'sales-pending-delivery':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/sales/sales-pending-delivery.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/sales/sales-pending-delivery.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/sales/sales-pending-delivery.php</div>";
                    }
                    break;
                case 'sales-return-list':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/sales/sales-return-list.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/sales/sales-return-list.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/sales/sales-return-list.php</div>";
                    }
                    break;
                case 'pos-sale':
                    // POS might need full screen or specific layout fixes
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/sales/pos-sale.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/sales/pos-sale.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/sales/pos-sale.php</div>";
                    }
                    break;
                case 'sales-invoice':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/sales/sales-invoice.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/sales/sales-invoice.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/sales/sales-invoice.php</div>";
                    }
                    break;
                case 'edit-sales':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/sales/edit-sales.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/sales/edit-sales.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/sales/edit-sales.php</div>";
                    }
                    break;
                case 'add-sales-return':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/sales/add-sales-return.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/sales/add-sales-return.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/sales/add-sales-return.php</div>";
                    }
                    break;
                case 'edit-sales-return':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/sales/edit-sales-return.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/sales/edit-sales-return.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/sales/edit-sales-return.php</div>";
                    }
                    break;
                case 'sales-return-invoice':
                    if (file_exists(FRONTEND_INVENTORY_PATH . 'templates/sales/sales-return-invoice.php')) {
                         include FRONTEND_INVENTORY_PATH . 'templates/sales/sales-return-invoice.php';
                    } else {
                        echo "<div class='p-4 bg-red-100 text-red-700'>Error: File not found: templates/sales/sales-return-invoice.php</div>";
                    }
                    break;
                case 'view-purchase':
                    include FRONTEND_INVENTORY_PATH . 'templates/purchase/view-purchase.php';
                    break;
                case 'add-purchase':
                    include FRONTEND_INVENTORY_PATH . 'templates/purchase/add-purchase.php';
                    break;
                case 'purchase-ordered-list':
                    include FRONTEND_INVENTORY_PATH . 'templates/purchase/purchase-ordered-list.php';
                    break;
                case 'purchase-pending-list':
                    include FRONTEND_INVENTORY_PATH . 'templates/purchase/purchase-pending-list.php';
                    break;
                case 'purchase-return-list':
                    include FRONTEND_INVENTORY_PATH . 'templates/purchase/purchase-return-list.php';
                    break;
                case 'edit-purchase':
                    include FRONTEND_INVENTORY_PATH . 'templates/purchase/edit-purchase.php';
                    break;
                case 'purchase-invoice':
                    include FRONTEND_INVENTORY_PATH . 'templates/purchase/purchase-invoice.php';
                    break;
                case 'add-purchase-return':
                    include FRONTEND_INVENTORY_PATH . 'templates/purchase/add-purchase-return.php';
                    break;
                case 'edit-purchase-return':
                    include FRONTEND_INVENTORY_PATH . 'templates/purchase/edit-purchase-return.php';
                    break;
                case 'purchase-return-invoice':
                    include FRONTEND_INVENTORY_PATH . 'templates/purchase/purchase-return-invoice.php';
                    break;
                
                // Stock
                case 'adjustment-list':
                    include plugin_dir_path(__FILE__) . 'stock/adjustment-list.php';
                    break;
                case 'add-adjustment':
                    include plugin_dir_path(__FILE__) . 'stock/add-adjustment.php';
                    break;
                case 'adjustment-invoice':
                    include plugin_dir_path(__FILE__) . 'stock/adjustment-invoice.php';
                    break;
                case 'edit-adjustment':
                    include plugin_dir_path(__FILE__) . 'stock/edit-adjustment.php';
                    break;
                case 'transfer-list':
                    include plugin_dir_path(__FILE__) . 'stock/transfer-list.php';
                    break;
                case 'add-transfer':
                    include plugin_dir_path(__FILE__) . 'stock/add-transfer.php';
                    break;
                case 'transfer-invoice':
                    include plugin_dir_path(__FILE__) . 'stock/transfer-invoice.php';
                    break;
                case 'edit-transfer':
                    include plugin_dir_path(__FILE__) . 'stock/edit-transfer.php';
                    break;
                
                // Reports
                case 'all-reports':
                    include plugin_dir_path(__FILE__) . 'reports/all-reports.php';
                    break;
                case 'sales-report':
                    include plugin_dir_path(__FILE__) . 'reports/sales-report.php';
                    break;
                case 'purchase-report':
                    include plugin_dir_path(__FILE__) . 'reports/purchase-report.php';
                    break;
                case 'stock-report':
                    include plugin_dir_path(__FILE__) . 'reports/stock-report.php';
                    break;
                case 'profit-loss-report':
                    include plugin_dir_path(__FILE__) . 'reports/profit-loss-report.php';
                    break;
                case 'customer-due-report':
                    include plugin_dir_path(__FILE__) . 'reports/customer-due-report.php';
                    break;
                case 'sales-payment-report':
                    include plugin_dir_path(__FILE__) . 'reports/sales-payment-report.php';
                    break;
                case 'customer-payment-report':
                    include plugin_dir_path(__FILE__) . 'reports/customer-payment-report.php';
                    break;
                case 'supplier-payment-report':
                    include plugin_dir_path(__FILE__) . 'reports/supplier-payment-report.php';
                    break;
                case 'supplier-due-report':
                    include plugin_dir_path(__FILE__) . 'reports/supplier-due-report.php';
                    break;
                case 'sales-summary-report':
                    include plugin_dir_path(__FILE__) . 'reports/sales-summary-report.php';
                    break;
                case 'stock-transfer-report':
                    include plugin_dir_path(__FILE__) . 'reports/stock-transfer-report.php';
                    break;
                case 'journal-report':
                    include plugin_dir_path(__FILE__) . 'reports/journal-report.php';
                    break;
                case 'trial-balance-report':
                    include plugin_dir_path(__FILE__) . 'reports/trial-balance-report.php';
                    break;
                case 'income-statement-report':
                    include plugin_dir_path(__FILE__) . 'reports/income-statement-report.php';
                    break;
                case 'balance-sheet-report':
                    include plugin_dir_path(__FILE__) . 'reports/balance-sheet-report.php';
                    break;
                case 'ledger-report':
                    include plugin_dir_path(__FILE__) . 'reports/ledger-report.php';
                    break;

                case 'all-features':
                    // define built-in inventory features with icons (Settings moved to top, removed Taxes & Backup)
                    $features = [
                        ['name'=>'Settings','icon'=>'fa-gear'],
                        ['name'=>'Items Management','icon'=>'fa-box'],
                        ['name'=>'Stock Management','icon'=>'fa-warehouse'],
                        ['name'=>'Sales','icon'=>'fa-shopping-cart'],
                        ['name'=>'Purchases','icon'=>'fa-handshake'],
                        ['name'=>'Reports','icon'=>'fa-chart-bar'],
                        ['name'=>'Contacts','icon'=>'fa-address-book'],
                    ];
                    ?>
                    <div class="container mt-4">
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h4 class="card-title mb-0">All Features</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3 text-end">
                                            <button id="printBtn" class="btn btn-sm btn-secondary me-2"><i class="fa-solid fa-print"></i> Print</button>
                                            <button id="pdfBtn" class="btn btn-sm btn-danger"><i class="fa-solid fa-file-pdf"></i> PDF</button>
                                        </div>
                                        <table id="featuresTable" class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Feature</th>
                                                    <th>Icon</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ( $features as $idx => $feat ) : ?>
                                                    <tr>
                                                        <td><?php echo $idx+1; ?></td>
                                                        <td><?php echo esc_html($feat['name']); ?></td>
                                                        <td><i class="fa-solid <?php echo esc_attr($feat['icon']); ?>"></i></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                    // scripts for print/pdf
                    ?>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
                    <script>
                    jQuery(document).ready(function($){
                        // prepare client-side data array for PDF export
                        var featuresData = <?php echo json_encode($features); ?>;

                        $('#printBtn').on('click', function(){
                            var w = window.open('', '_blank');
                            w.document.write('<html><head><title>Print Features</title>');
                            w.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>');
                            w.document.write('<style>table{width:100%;border-collapse:collapse;}th,td{border:1px solid #000;padding:4px;text-align:left;}</style>');
                            w.document.write('</head><body>');
                            w.document.write($('#featuresTable').prop('outerHTML'));
                            w.document.write('</body></html>');
                            w.document.close();
                            w.print();
                            w.close();
                        });
                        $('#pdfBtn').on('click', function(){
                            const iconMap = {
                                'fa-gear':'\uf013',
                                'fa-box':'\uf466',
                                'fa-warehouse':'\uf494',
                                'fa-shopping-cart':'\uf07a',
                                'fa-handshake':'\uf2b5',
                                'fa-chart-bar':'\uf080',
                                'fa-address-book':'\uf2b9'
                            };
                            const { jsPDF } = window.jspdf;
                            const doc = new jsPDF();
                            // ensure FontAwesome unicode font
                            doc.setFont('helvetica');
                            var body = featuresData.map(function(f,i){
                                var iconChar = iconMap[f.icon] || '';
                                return [i+1, f.name, iconChar];
                            });
                            doc.autoTable({ head:[['#','Feature','Icon']], body: body });
                            doc.save('features.pdf');
                        });
                    });
                    </script>
                    <?php
                    break;

                case 'dashboard':
                default:
            ?>
            <?php
                global $wpdb;
                $prefix = $wpdb->prefix;
                
                // Fetch Stats
                $total_sales = $wpdb->get_var("SELECT SUM(grand_total) FROM {$prefix}orabooks_db_sales WHERE status = 1") ?: 0;
                $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}orabooks_db_items WHERE status = 1") ?: 0;
                $total_customers = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}orabooks_db_customers WHERE status = 1") ?: 0;
                $pending_orders = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}orabooks_db_purchase WHERE purchase_status != 'Received' AND status = 1") ?: 0;

                // Chart Data: Yearly Sale (Month-wise)
                $current_year = date('Y');
                $monthly_sales_query = "
                    SELECT MONTH(sales_date) as month, SUM(grand_total) as total 
                    FROM {$prefix}orabooks_db_sales 
                    WHERE YEAR(sales_date) = %d AND status = 1
                    GROUP BY MONTH(sales_date) 
                    ORDER BY MONTH(sales_date) ASC";
                $monthly_sales_raw = $wpdb->get_results($wpdb->prepare($monthly_sales_query, $current_year));
                
                $monthly_sales = array_fill(1, 12, 0);
                foreach ($monthly_sales_raw as $row) {
                    $monthly_sales[(int)$row->month] = (float)$row->total;
                }
                $monthly_sales_json = json_encode(array_values($monthly_sales));

                // Chart Data: Item-wise Sale (Pie Chart) - Top 5 items
                $item_wise_query = "
                    SELECT i.item_name, SUM(si.sales_qty) as total_qty 
                    FROM {$prefix}orabooks_db_salesitems si
                    JOIN {$prefix}orabooks_db_items i ON si.item_id = i.id
                    JOIN {$prefix}orabooks_db_sales s ON si.sales_id = s.id
                    WHERE s.status = 1
                    GROUP BY si.item_id 
                    ORDER BY total_qty DESC 
                    LIMIT 5";
                $item_wise_raw = $wpdb->get_results($item_wise_query);
                
                $item_labels = [];
                $item_data = [];
                
                if (!empty($item_wise_raw)) {
                    foreach ($item_wise_raw as $row) {
                        $item_labels[] = $row->item_name;
                        $item_data[] = (float)$row->total_qty;
                    }
                } else {
                    // Fallback for empty data
                    $item_labels = ['No Sales Yet'];
                    $item_data = [1];
                }
                
                $item_labels_json = json_encode($item_labels);
                $item_data_json = json_encode($item_data);
            ?>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Dashboard Overview</h1>
                <div class="mt-4 md:mt-0 flex space-x-2">
                    <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded flex items-center">
                        <i class="fa-regular fa-calendar-days mr-1"></i> <?php echo date('F Y'); ?>
                    </span>
                </div>
            </div>
            
            <div class="stats-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6">
                <!-- Stats Card 1 -->
                <div class="stats-card bg-white rounded-lg shadow-sm p-4 md:p-6 border-l-4 border-blue-500 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-1">Total Sales</p>
                            <h2 class="text-2xl font-bold text-gray-800"><?php echo number_format($total_sales, 2); ?></h2>
                        </div>
                        <div class="bg-blue-50 p-3 rounded-full text-blue-500">
                            <i class="fa-solid fa-dollar-sign text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-xs text-blue-600">
                        <i class="fa-solid fa-arrow-trend-up mr-1"></i>
                        <span>Overall performance</span>
                    </div>
                </div>

                <!-- Stats Card 2 -->
                <div class="stats-card bg-white rounded-lg shadow-sm p-4 md:p-6 border-l-4 border-green-500 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-1">Total Items</p>
                            <h2 class="text-2xl font-bold text-gray-800"><?php echo number_format($total_items); ?></h2>
                        </div>
                        <div class="bg-green-50 p-3 rounded-full text-green-500">
                            <i class="fa-solid fa-box text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-xs text-green-600">
                        <a href="?view=view-items" class="hover:underline">View inventory <i class="fa-solid fa-chevron-right ml-1"></i></a>
                    </div>
                </div>

                <!-- Stats Card 3 -->
                <div class="stats-card bg-white rounded-lg shadow-sm p-4 md:p-6 border-l-4 border-purple-500 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-1">Total Customers</p>
                            <h2 class="text-2xl font-bold text-gray-800"><?php echo number_format($total_customers); ?></h2>
                        </div>
                        <div class="bg-purple-50 p-3 rounded-full text-purple-500">
                            <i class="fa-solid fa-users text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-xs text-purple-600">
                        <a href="?view=customers" class="hover:underline">Manage customers <i class="fa-solid fa-chevron-right ml-1"></i></a>
                    </div>
                </div>

                <!-- Stats Card 4 -->
                <div class="stats-card bg-white rounded-lg shadow-sm p-4 md:p-6 border-l-4 border-yellow-500 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-1">Pending Orders</p>
                            <h2 class="text-2xl font-bold text-gray-800"><?php echo number_format($pending_orders); ?></h2>
                        </div>
                        <div class="bg-yellow-50 p-3 rounded-full text-yellow-500">
                            <i class="fa-solid fa-hourglass-half text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-xs text-yellow-600">
                        <a href="?view=view-purchase" class="hover:underline">Track orders <i class="fa-solid fa-chevron-right ml-1"></i></a>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-grid grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6 mb-6">
                <!-- Bar Chart: Yearly Sales -->
                <div class="chart-container lg:col-span-2 bg-white rounded-lg shadow-sm p-4 md:p-6 border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-gray-700">Yearly Sales (<?php echo $current_year; ?>)</h2>
                        <i class="fa-solid fa-chart-bar text-gray-400"></i>
                    </div>
                    <div class="h-64 md:h-80 relative">
                        <canvas id="yearlySalesChart"></canvas>
                    </div>
                </div>

                <!-- Pie Chart: Item-wise Sale -->
                <div class="chart-container bg-white rounded-lg shadow-sm p-4 md:p-6 border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-gray-700">Top Selling Items</h2>
                        <i class="fa-solid fa-chart-pie text-gray-400"></i>
                    </div>
                    <div class="h-64 md:h-80 relative">
                        <canvas id="itemWiseSaleChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Reports Summary -->
             <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-gray-700">Quick Reports</h2>
                        <i class="fa-solid fa-file-lines text-gray-400"></i>
                    </div>
                    <div class="space-y-4">
                        <a href="?view=sales-report" class="flex items-center justify-between p-3 rounded-lg border border-gray-50 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center">
                                <span class="w-8 h-8 bg-blue-50 text-blue-500 rounded flex items-center justify-center mr-3"><i class="fa-solid fa-cart-shopping"></i></span>
                                <span class="text-sm font-medium text-gray-700">Full Sales Report</span>
                            </div>
                            <i class="fa-solid fa-chevron-right text-gray-300 text-xs"></i>
                        </a>
                        <a href="?view=purchase-report" class="flex items-center justify-between p-3 rounded-lg border border-gray-50 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center">
                                <span class="w-8 h-8 bg-green-50 text-green-500 rounded flex items-center justify-center mr-3"><i class="fa-solid fa-truck"></i></span>
                                <span class="text-sm font-medium text-gray-700">Purchase Report</span>
                            </div>
                            <i class="fa-solid fa-chevron-right text-gray-300 text-xs"></i>
                        </a>
                        <a href="?view=stock-report" class="flex items-center justify-between p-3 rounded-lg border border-gray-50 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center">
                                <span class="w-8 h-8 bg-orange-50 text-orange-500 rounded flex items-center justify-center mr-3"><i class="fa-solid fa-boxes-stacked"></i></span>
                                <span class="text-sm font-medium text-gray-700">Stock Inventory Report</span>
                            </div>
                            <i class="fa-solid fa-chevron-right text-gray-300 text-xs"></i>
                        </a>
                        <a href="?view=profit-loss-report" class="flex items-center justify-between p-3 rounded-lg border border-gray-50 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center">
                                <span class="w-8 h-8 bg-purple-50 text-purple-500 rounded flex items-center justify-center mr-3"><i class="fa-solid fa-file-invoice"></i></span>
                                <span class="text-sm font-medium text-gray-700">Profit & Loss Summary</span>
                            </div>
                            <i class="fa-solid fa-chevron-right text-gray-300 text-xs"></i>
                        </a>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-100 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center mr-3">
                                <i class="fa-solid fa-lightbulb text-xl"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-800">Inventory Insight</h3>
                        </div>
                        <p class="text-gray-500 text-sm mb-6">
                            <?php 
                                if ($total_items > 0 && $total_sales > 0) {
                                    echo "You currently have <span class='font-bold text-gray-800'>" . number_format($total_items) . "</span> items in stock with a total sales volume of <span class='font-bold text-gray-800'>" . number_format($total_sales, 2) . "</span>.";
                                } else {
                                    echo "Welcome to your new dashboard! Start by adding items and making sales to see real-time data here.";
                                }
                            ?>
                        </p>
                    </div>

                    <div class="border-t border-gray-50 pt-4">
                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 text-left">Quick Actions</h4>
                        <div class="grid grid-cols-3 gap-2">
                            <a href="?view=add-sale" class="flex flex-col items-center p-2 rounded-lg bg-blue-50 hover:bg-blue-600 text-blue-600 hover:text-white transition-all group">
                                <i class="fa-solid fa-cart-plus mb-1"></i>
                                <span class="text-[10px] font-bold">Sale</span>
                            </a>
                            <a href="?view=add-item" class="flex flex-col items-center p-2 rounded-lg bg-green-50 hover:bg-green-600 text-green-600 hover:text-white transition-all group">
                                <i class="fa-solid fa-plus mb-1"></i>
                                <span class="text-[10px] font-bold">Item</span>
                            </a>
                            <a href="?view=pos-sale" class="flex flex-col items-center p-2 rounded-lg bg-orange-50 hover:bg-orange-600 text-orange-600 hover:text-white transition-all group">
                                <i class="fa-solid fa-cash-register mb-1"></i>
                                <span class="text-[10px] font-bold">POS</span>
                            </a>
                            <a href="?view=customers" class="flex flex-col items-center p-2 rounded-lg bg-purple-50 hover:bg-purple-600 text-purple-600 hover:text-white transition-all group">
                                <i class="fa-solid fa-user-plus mb-1"></i>
                                <span class="text-[10px] font-bold">Cust</span>
                            </a>
                            <a href="?view=all-reports" class="flex flex-col items-center p-2 rounded-lg bg-red-50 hover:bg-red-600 text-red-600 hover:text-white transition-all group">
                                <i class="fa-solid fa-file-invoice-dollar mb-1"></i>
                                <span class="text-[10px] font-bold">Reports</span>
                            </a>
                            <a href="?view=store-profile" class="flex flex-col items-center p-2 rounded-lg bg-gray-100 hover:bg-gray-600 text-gray-600 hover:text-white transition-all group">
                                <i class="fa-solid fa-gear mb-1"></i>
                                <span class="text-[10px] font-bold">Setup</span>
                            </a>
                        </div>
                    </div>
                </div>
             </div>

            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                jQuery(document).ready(function($) {
                    // Yearly Sales Chart
                    const canvasSales = document.getElementById('yearlySalesChart');
                    if (canvasSales) {
                        const ctxSales = canvasSales.getContext('2d');
                        new Chart(ctxSales, {
                            type: 'bar',
                            data: {
                                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                                datasets: [{
                                    label: 'Monthly Sales Amount',
                                    data: <?php echo $monthly_sales_json; ?>,
                                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                                    borderColor: 'rgb(59, 130, 246)',
                                    borderWidth: 1,
                                    borderRadius: 4
                                }]
                            },
                            options: {
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        grid: { color: 'rgba(0, 0, 0, 0.05)' }
                                    },
                                    x: {
                                        grid: { display: false }
                                    }
                                },
                                plugins: {
                                    legend: { display: false }
                                }
                            }
                        });
                    }

                    // Item-wise Sale Pie Chart
                    const canvasPie = document.getElementById('itemWiseSaleChart');
                    if (canvasPie) {
                        const ctxPie = canvasPie.getContext('2d');
                        new Chart(ctxPie, {
                            type: 'doughnut',
                            data: {
                                labels: <?php echo $item_labels_json; ?>,
                                datasets: [{
                                    data: <?php echo $item_data_json; ?>,
                                    backgroundColor: [
                                        'rgba(59, 130, 246, 0.8)',
                                        'rgba(16, 185, 129, 0.8)',
                                        'rgba(139, 92, 246, 0.8)',
                                        'rgba(245, 158, 11, 0.8)',
                                        'rgba(239, 68, 68, 0.8)'
                                    ],
                                    hoverOffset: 4
                                }]
                            },
                            options: {
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        labels: {
                                            usePointStyle: true,
                                            padding: 20,
                                            font: { size: 12 }
                                        }
                                    }
                                }
                            }
                        });
                    }
                });
            </script>
            <?php
                    break;
            }
            ?>
        </main>
    </div>

    <?php wp_footer(); ?>
</body>
</html>
