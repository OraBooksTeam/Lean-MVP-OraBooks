/**
 * Dashboard Core Logic - PWA Style
 */

console.log('dashboard.js loaded');

(function($) {
    'use strict';

    const Dashboard = {
        sections: {
            overview: { title: 'Overview', icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z' },
            posts: { title: 'Posts', endpoint: '/posts' },
            pages: { title: 'Pages', endpoint: '/pages' },
            users: { title: 'Users', endpoint: '/users' },
            media: { title: 'Media Library', endpoint: '/media' },
            settings: { title: 'Settings', endpoint: '/site-settings' },
            profile: { title: 'My Profile', endpoint: '/users/me' },
            security: { title: 'Security', endpoint: '/dashboard/security' },
            upgrade: { title: 'Upgrade Plan', endpoint: '/dashboard/levels' },
            checkout: { title: 'Checkout', endpoint: '/dashboard/checkout' },
            addons: { title: 'Addons', endpoint: '/dashboard/features' }
        },

        init: function() {
            console.log('🚀 WPFD Dashboard initializing...');
            window.loadSection = (s, p) => this.loadSection(s, p);
            window.quickAction = (a) => this.quickAction(a);
            window.openAdminPage = (url) => this.openAdminPage(url);
            window.toggleMobileSidebar = () => this.toggleMobileSidebar();
            window.toggleQuickMenu = () => this.toggleQuickMenu();
            window.closeModal = () => this.closeModal();
            window.hideSearchResults = () => this.hideSearchResults();
            window.Dashboard = this;
            this.setupSearch();
            this.setupNotifications();
            this.interceptLinks();
            this.setupMobileTouchSupport();
            
                // Debug: Check if interceptLinks is properly set up
                setTimeout(() => {
                    console.log('🔧 interceptLinks setup complete');
                    console.log('🔧 Available global functions:', {
                        loadSection: typeof window.loadSection,
                        quickAction: typeof window.quickAction,
                        openAdminPage: typeof window.openAdminPage
                    });
                }, 1000);
        },

        setupMobileTouchSupport: function() {
            // Add touch event support for avatar upload buttons
            $(document).on('touchstart click', '[onclick*="openAvatarUpload"], button[onclick*="openAvatarUpload"]', function(e) {
                e.preventDefault();
                console.log('Touch/click event triggered on avatar upload button');
                Dashboard.openAvatarUpload();
            });
        },

        toggleMobileSidebar: function() {
            const $sidebar = $('#mobile-sidebar');
            const $overlay = $('#sidebar-overlay');
            const $content = $('#sidebar-content');
            const isOpen = !$sidebar.hasClass('pointer-events-none');

            if (isOpen) {
                $sidebar.addClass('pointer-events-none');
                $content.addClass('-translate-x-full');
                $overlay.addClass('opacity-0');
            } else {
                $sidebar.removeClass('pointer-events-none');
                $content.removeClass('-translate-x-full');
                $overlay.removeClass('opacity-0');
            }
        },

        toggleQuickMenu: function() {
            const $menu = $('#quick-menu');
            const isHidden = $menu.hasClass('hidden');

            if (isHidden) {
                $menu.removeClass('hidden');
                setTimeout(() => $menu.removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100'), 10);
            } else {
                $menu.addClass('scale-95 opacity-0').removeClass('scale-100 opacity-100');
                setTimeout(() => $menu.addClass('hidden'), 200);
            }
        },

        interceptLinks: function() {
            $(document).on('click', '#section-content a', (e) => {
                const href = $(e.currentTarget).attr('href');
                if (!href || href === '#' || href.startsWith('javascript:')) {
                    return;
                }

                const url = new URL(href, window.location.origin);
                const params = new URLSearchParams(url.search);
                const paramObj = {};
                for (const [key, value] of params) {
                    paramObj[key] = value;
                }
                
                // Intercept post.php edit links - load in iframe within dashboard
                // AccessManager allows ?wpfd_iframe=1 through even on multisite
                if (url.pathname.includes('post.php') && paramObj.action === 'edit') {
                    e.preventDefault();
                    const postType = paramObj.post_type || 'post';
                    const editTitle = postType === 'page' ? 'Edit Page' : 'Edit Post';
                    url.searchParams.set('wpfd_iframe', '1');
                    this.loadIframe(url.toString(), editTitle);
                    return;
                }
                
                // Intercept post-new.php links - load in iframe within dashboard
                if (url.pathname.includes('post-new.php')) {
                    e.preventDefault();
                    const postType = paramObj.post_type || 'post';
                    const newTitle = postType === 'page' ? 'New Page' : 'New Post';
                    url.searchParams.set('wpfd_iframe', '1');
                    this.loadIframe(url.toString(), newTitle);
                    return;
                }
                
                // Intercept activation links and load them in an iframe
                if (paramObj.action === 'orabooks_activate_free_plan') {
                    e.preventDefault();
                    this.loadIframe(href, 'Activating Plan...');
                    return;
                }

                // If it's a membership related link, stay in upgrade section
                if (paramObj.orabooks_group || url.search.includes('orabooks_group')) {
                    e.preventDefault();
                    this.loadSection('upgrade', { orabooks_group: paramObj.orabooks_group });
                    return;
                }
                
                // Intercept "Back to All Groups" link
                if ($(e.currentTarget).hasClass('orabooks-back-link')) {
                    e.preventDefault();
                    this.loadSection('upgrade');
                    return;
                }
                
                // Intercept checkout links
                if (params.join_level || url.pathname.includes('orabooks-checkout')) {
                    e.preventDefault();
                    this.loadIframe(href, 'Checkout');
                    return;
                }
            });

            // Helper to convert URLSearchParams to object
            if (!Object.from_object) {
                Object.from_object = (params) => {
                    const obj = {};
                    params.forEach((v, k) => obj[k] = v);
                    return obj;
                };
            }
        },

        loadIframe: function(href, title = 'Loading...') {
            const $container = $('#section-content');
            $container.addClass('section-hidden');
            
            // Clean up potentially malformed URLs (double prefixes)
            let cleanHref = href;
            const matches = href.match(/(https?:\/\/[^\/]+)\/.*(https?:\/\/.*)/i);
            if (matches && matches[2]) {
                cleanHref = matches[2];
            }

            // Append iframe flag to handle styling inside the iframe
            const iframeUrl = new URL(cleanHref, window.location.origin);
            iframeUrl.searchParams.set('wpfd_iframe', '1');

            setTimeout(() => {
                $container.html(`
                    <div class="h-[calc(100vh-160px)] w-full overflow-hidden rounded-3xl bg-white shadow-premium border border-gray-100">
                        <iframe src="${iframeUrl.toString()}" class="w-full h-full border-none" id="content-iframe"></iframe>
                    </div>
                `);
                $container.removeClass('section-hidden');
                $('#page-title').text(title);
            }, 200);
        },

        openAdminPage: function(url) {
            console.log('🔧 openAdminPage called with:', {
                inputUrl: url,
                adminBase: wpfdVars.adminBase
            });
            
            // Build the full admin URL
            let adminUrl;
            if (url.startsWith('http://') || url.startsWith('https://')) {
                adminUrl = new URL(url);
            } else {
                adminUrl = new URL(wpfdVars.adminBase + url);
            }
            
            const urlPath = adminUrl.pathname;
            const urlSearch = adminUrl.search;
            
            // For edit content links (post.php with action=edit), load in iframe within dashboard
            // AccessManager already allows ?wpfd_iframe=1 requests through (see AccessManager.php line 256)
            if (urlPath.includes('post.php') && adminUrl.searchParams.get('action') === 'edit') {
                const postType = adminUrl.searchParams.get('post_type') || 'post';
                const editTitle = postType === 'page' ? 'Edit Page' : 'Edit Post';
                adminUrl.searchParams.set('wpfd_iframe', '1');
                console.log('🚀 Loading edit page in iframe:', adminUrl.toString());
                this.loadIframe(adminUrl.toString(), editTitle);
                return;
            }
            
            // For post-new.php (create new), also load in iframe
            if (urlPath.includes('post-new.php')) {
                const postType = adminUrl.searchParams.get('post_type') || 'post';
                const newTitle = postType === 'page' ? 'New Page' : 'New Post';
                adminUrl.searchParams.set('wpfd_iframe', '1');
                console.log('🚀 Loading new content page in iframe:', adminUrl.toString());
                this.loadIframe(adminUrl.toString(), newTitle);
                return;
            }
            
            // For user-new.php, redirect to full wp-admin (requires full context)
            if (urlPath.includes('user-new.php')) {
                console.log('🚀 Redirecting to WordPress user creation page:', adminUrl.toString());
                window.location.href = adminUrl.toString();
                return;
            }
            
            // For other admin pages, load in iframe
            adminUrl.searchParams.set('wpfd_iframe', '1');
            
            let title = 'Editor';
            let icon = 'edit';
            
            if (urlPath.includes('media-new.php')) {
                title = 'Upload Media';
                icon = 'upload';
            } else if (urlPath.includes('upload.php')) {
                title = 'Media Library';
                icon = 'image';
            } else if (urlPath.includes('edit-comments.php')) {
                title = 'Comments';
                icon = 'message';
            } else if (urlPath.includes('options-general.php')) {
                title = 'Settings';
                icon = 'settings';
            }
            
            console.log('🚀 Loading iframe with URL:', adminUrl.toString());
            this.loadIframe(adminUrl.toString(), title);
            
            const icons = {
                'edit': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>',
                'file': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path>',
                'document': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2z"></path><polyline points="14 2 14 8 20 8"></polyline>',
                'upload': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>',
                'image': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>',
                'message': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>',
                'user': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>',
                'settings': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>'
            };
            
            this.showModal(title, `
                <div class="relative w-full h-full">
                    <!-- Loading indicator -->
                    <div id="admin-loading" class="absolute inset-0 flex items-center justify-center bg-gray-50 z-10">
                        <div class="text-center">
                            <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-primary-500 border-t-transparent mb-4"></div>
                            <p class="text-gray-600 font-medium">Loading WordPress Admin...</p>
                        </div>
                    </div>
                    <!-- iframe with WordPress admin -->
                    <iframe 
                        src="${adminUrl.toString()}" 
                        class="w-full h-full border-none bg-white" 
                        id="admin-iframe"
                        onload="document.getElementById('admin-loading')?.remove()"
                        onerror="document.getElementById('admin-loading').innerHTML='<p class=\\'text-red-500\\'>Failed to load. Please try again.</p>'"
                    ></iframe>
                </div>
            `, true, icon);
        },

        loadSection: function(sectionKey, params = {}) {
            const section = this.sections[sectionKey] || { title: sectionKey };
            $('#page-title').text(section.title);
            
            // Update Active States
            $('.nav-btn, .mobile-nav-btn').removeClass('bg-primary-500 text-white shadow-lg active-nav shadow-primary-500/20').addClass('text-gray-400');
            const $active = $(`[data-section="${sectionKey}"]`);
            $active.addClass('active-nav').removeClass('text-gray-400');
            
            // Apply background/shadow only to sidebar buttons (not in the fixed bottom nav)
            $active.each(function() {
                const $btn = $(this);
                if (!$btn.closest('nav.fixed.bottom-0').length) {
                    $btn.addClass('bg-primary-500 text-white shadow-lg shadow-primary-500/20');
                }
            });

            // Hide mobile sidebar if open
            if (window.toggleMobileSidebar && !$('#mobile-sidebar').hasClass('pointer-events-none')) {
                window.toggleMobileSidebar();
            }

            const $container = $('#section-content');
            $container.addClass('section-hidden');

            setTimeout(() => {
                console.log('loadSection called for:', sectionKey, 'section config:', section);

                if (sectionKey === 'overview') {
                    console.log('Reloading page for overview');
                    location.reload();
                    return;
                }

                // If section has a URL, load it in an iframe
                if (section.url) {
                    console.log('Loading URL in iframe:', section.url);
                    this.loadIframe(section.url, section.title);
                    return;
                }

                const endpoint = section.endpoint || `/${sectionKey}`;
                this.fetchData(endpoint, params).then(data => {
                    $container.html(this.renderContent(sectionKey, data));
                    $container.removeClass('section-hidden');
                }).catch(err => {
                    $container.html(`<div class="p-8 text-center text-red-500">Error loading ${section.title}. Please try again.</div>`);
                    $container.removeClass('section-hidden');
                });
            }, 200);
        },

        fetchData: async function(endpoint, params = {}) {
            let url = `${wpfdVars.restUrl}${endpoint}`;
            const queryParams = new URLSearchParams(params).toString();
            if (queryParams) {
                url += (url.includes('?') ? '&' : '?') + queryParams;
            }

            const response = await fetch(url, {
                headers: { 'X-WP-Nonce': wpfdVars.restNonce },
                credentials: 'same-origin'
            });
            if (!response.ok) throw new Error('Network response was not ok');
            return await response.json();
        },

        renderContent: function(section, data) {
            switch(section) {
                case 'posts':
                case 'pages':
                    return this.renderTable(section, data);
                case 'users':
                    return this.renderUsers(data);
                case 'media':
                    return this.renderMedia(data);
                case 'settings':
                    return this.renderSettings(data);
                case 'security':
                    return this.renderSecurity(data);
                case 'profile':
                    return this.renderProfile(data);
                case 'upgrade':
                    return this.renderUpgrade(data);
                case 'checkout':
                    return this.renderCheckout(data);
                case 'addons':
                    return this.renderAddons(data);
                default:
                    return `<div class="p-8 text-center text-gray-500">Coming soon: ${section} content</div>`;
            }
        },

        renderTable: function(type, data) {
            const items = data.items || data;
            let html = `
                <div class="bg-white rounded-3xl shadow-premium border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 flex justify-between items-center">
                        <h3 class="font-bold text-gray-900">${type.charAt(0).toUpperCase() + type.slice(1)}</h3>
                        <button onclick="quickAction('new-${type.slice(0,-1)}')" class="text-sm font-bold text-primary-600">+ New ${type.slice(0,-1)}</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50/50 text-[10px] uppercase tracking-widest text-gray-400 font-bold">
                                    <th class="px-6 py-4">Title</th>
                                    <th class="px-6 py-4">Status</th>
                                    <th class="px-6 py-4">Date</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">`;
            
            if (items.length === 0) {
                html += `<tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">No items found</td></tr>`;
            } else {
                // Determine the post_type param needed for pages
                const postTypeParam = type === 'pages' ? '&post_type=page' : '';
                const itemTypeSingular = type === 'pages' ? 'page' : 'post';
                const editLabel = type === 'pages' ? 'Edit Page' : 'Edit Post';
                const deleteLabel = type === 'pages' ? 'Delete Page' : 'Delete Post';

                items.forEach(item => {
                    const statusColor = item.status === 'publish' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700';
                    html += `
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-900">${item.title}</div>
                                <div class="text-xs text-gray-400">${item.author || ''}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded-lg text-[10px] font-bold uppercase ${statusColor}">${item.status}</span>
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-500">${new Date(item.date).toLocaleDateString()}</td>
                            <td class="px-6 py-4 text-right">
                                <button onclick="openAdminPage('post.php?post=${item.id}&action=edit${postTypeParam}')" class="p-2 text-gray-400 hover:text-primary-600 transition" title="${editLabel}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                    </svg>
                                </button>
                                <button onclick="Dashboard.deleteItem(${item.id}, '${itemTypeSingular}')" class="p-2 text-gray-400 hover:text-red-600 transition" title="${deleteLabel}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </td>
                        </tr>`;
                });
            }
            
            html += `</tbody></table></div></div>`;
            return html;
        },

        renderUpgrade: function(data) {
            const groups = data.groups || [];
            let html = `
                <div class="space-y-6">
                    <div class="bg-gradient-to-r from-primary-600 to-indigo-700 rounded-3xl p-8 text-white shadow-premium relative overflow-hidden">
                        <div class="relative z-10">
                            <h1 class="text-3xl font-bold mb-2">Upgrade Your Plan</h1>
                            <p class="text-primary-100 text-lg">Choose the perfect plan for your business needs.</p>
                        </div>
                        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
                    </div>`;

            if (groups.length === 0) {
                html += `<div class="bg-white rounded-3xl p-12 shadow-premium border border-gray-100 text-center">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">No Plans Available</h3>
                    <p class="text-gray-500">No membership plans have been configured yet.</p>
                </div>`;
            }

            groups.forEach(group => {
                html += `
                    <div class="bg-white rounded-3xl p-8 shadow-premium border border-gray-100">
                        <div class="mb-8">
                            <h2 class="text-2xl font-bold text-gray-900">${this.escapeHtml(group.name)}</h2>
                            ${group.description ? `<p class="text-gray-500 mt-1">${this.escapeHtml(group.description)}</p>` : ''}
                        </div>`;

                if (group.levels && group.levels.length > 0) {
                    html += `<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">`;
                    group.levels.forEach(level => {
                        const isFree = level.price == 0;
                        const priceDisplay = isFree ? 'Free' : level.currency_symbol + parseFloat(level.price).toFixed(2);
                        const billingText = isFree ? 'Forever' : 'per ' + level.billing_period;
                        const isPopular = level.label === 'Popular' || (!isFree && !level.label);
                        const levelUrl = wpfdVars.restUrl.replace('/wpfd/v1', '') + '/dashboard/checkout?join_level=' + level.id;

                        html += `
                            <div class="relative bg-gradient-to-b from-white to-gray-50 rounded-2xl p-6 border-2 ${isPopular ? 'border-primary-500 shadow-premium-hover' : 'border-gray-200 hover:border-primary-300'} transition-all duration-300 hover:shadow-premium-hover flex flex-col">
                                ${level.label ? `<div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-gradient-to-r from-primary-600 to-indigo-700 text-white px-4 py-1 rounded-full text-xs font-bold uppercase tracking-wider">${this.escapeHtml(level.label)}</div>` : ''}
                                <div class="text-center mb-6 mt-${level.label ? '4' : '0'}">
                                    <h3 class="text-xl font-bold text-gray-900">${this.escapeHtml(level.name)}</h3>
                                    ${level.description ? `<p class="text-sm text-gray-500 mt-1">${this.escapeHtml(level.description)}</p>` : ''}
                                </div>
                                <div class="text-center mb-6">
                                    <span class="text-4xl font-black text-gray-900">${priceDisplay}</span>
                                    ${!isFree ? `<span class="text-sm text-gray-500 ml-1">${billingText}</span>` : `<span class="text-sm text-green-600 ml-1">${billingText}</span>`}
                                </div>
                                <div class="mt-auto">
                                    <button onclick="Dashboard.loadSection('checkout', { join_level: ${level.id} })" class="w-full py-3 px-6 rounded-xl font-bold text-sm transition-all duration-300 ${isPopular ? 'bg-gradient-to-r from-primary-600 to-indigo-700 text-white shadow-lg shadow-primary-500/30 hover:shadow-xl hover:shadow-primary-500/40 hover:-translate-y-0.5' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 hover:-translate-y-0.5'}">
                                        ${isFree ? 'Get Started Free' : 'Choose Plan'}
                                    </button>
                                </div>
                            </div>`;
                    });
                    html += `</div>`;
                } else {
                    html += `<p class="text-center text-gray-400 py-8">No active plans in this category.</p>`;
                }
                html += `</div>`;
            });

            html += `</div>`;
            return html;
        },

        renderCheckout: function(data) {
            if (data.html) {
                return `<div class="bg-white rounded-3xl p-8 shadow-premium border border-gray-100">${data.html}</div>`;
            }

            if (data.payment_required === false && data.free) {
                const freeNonce = data.free_nonce || '';
                return `
                    <div class="max-w-lg mx-auto space-y-6">
                        <div class="bg-gradient-to-r from-primary-600 to-indigo-700 rounded-3xl p-8 text-white shadow-premium text-center">
                            <h1 class="text-3xl font-bold mb-2">Activate Free Plan</h1>
                            <p class="text-primary-100">Start with ${this.escapeHtml(data.level_name)} plan</p>
                        </div>
                        <div class="bg-white rounded-3xl p-8 shadow-premium border border-gray-100 text-center">
                            <div class="text-5xl font-black text-gray-900 mb-2">Free</div>
                            <p class="text-gray-500 mb-6">${this.escapeHtml(data.level_name)} - Forever</p>
                            ${data.level_description ? `<p class="text-gray-600 mb-6">${this.escapeHtml(data.level_description)}</p>` : ''}
                            <button onclick="Dashboard.handleFreeCheckout(${data.level}, '${freeNonce}')" class="w-full py-4 px-6 bg-gradient-to-r from-primary-600 to-indigo-700 text-white rounded-xl font-bold text-lg shadow-lg hover:shadow-xl transition-all">
                                Activate Free Plan
                            </button>
                        </div>
                    </div>`;
            }

            if (data.payment_required && data.gateways) {
                let gatewaysHtml = '';
                data.gateways.forEach((gw, i) => {
                    gatewaysHtml += `
                        <label class="gateway-option flex items-center gap-4 p-4 rounded-xl border-2 ${i === 0 ? 'border-primary-500 bg-primary-50' : 'border-gray-200'} cursor-pointer hover:border-primary-300 transition-all">
                            <input type="radio" name="payment_gateway" value="${this.escapeHtml(gw.id)}" ${i === 0 ? 'checked' : ''} class="w-5 h-5 text-primary-600">
                            <div>
                                <div class="font-bold text-gray-900">${this.escapeHtml(gw.title)}</div>
                                ${gw.description ? `<div class="text-sm text-gray-500">${this.escapeHtml(gw.description)}</div>` : ''}
                            </div>
                        </label>`;
                });

                return `
                    <div class="max-w-lg mx-auto space-y-6">
                        <div class="bg-gradient-to-r from-primary-600 to-indigo-700 rounded-3xl p-8 text-white shadow-premium text-center">
                            <h1 class="text-3xl font-bold mb-2">Complete Purchase</h1>
                            <p class="text-primary-100">You're about to join <strong>${this.escapeHtml(data.level_name)}</strong></p>
                        </div>
                        <div class="bg-white rounded-3xl p-8 shadow-premium border border-gray-100 space-y-6">
                            <div>
                                <h3 class="font-bold text-gray-900 mb-4 pb-3 border-b border-gray-100">Order Summary</h3>
                                <div class="flex justify-between py-2"><span class="text-gray-600">Plan:</span><span class="font-bold text-gray-900">${this.escapeHtml(data.level_name)}</span></div>
                                <div class="flex justify-between py-2"><span class="text-gray-600">Price:</span><span class="font-bold text-lg text-primary-600">${data.level_price_display}</span></div>
                                <div class="flex justify-between py-2"><span class="text-gray-600">Billing:</span><span class="text-gray-900">${data.billing_period}</span></div>
                                ${data.level_description ? `<div class="mt-4 pt-4 border-t border-gray-100 text-sm text-gray-500">${this.escapeHtml(data.level_description)}</div>` : ''}
                            </div>
                            ${gatewaysHtml ? `
                            <div>
                                <h3 class="font-bold text-gray-900 mb-4">Select Payment Method</h3>
                                <div class="space-y-3" id="gateway-options">
                                    ${gatewaysHtml}
                                </div>
                            </div>
                            <button id="complete-purchase-btn" data-level-id="${data.level}" data-nonce="${data.nonce || ''}" data-original-label="Complete Purchase - ${data.level_price_display}" onclick="Dashboard.handlePaidCheckout(${data.level})" class="w-full py-4 px-6 bg-gradient-to-r from-primary-600 to-indigo-700 text-white rounded-xl font-bold text-lg shadow-lg hover:shadow-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                Complete Purchase - ${data.level_price_display}
                            </button>
                            <p class="text-center text-xs text-gray-400">Secure payment processing</p>
                            ` : '<p class="text-center text-gray-500 py-4">No payment gateways available.</p>'}
                        </div>
                    </div>`;
            }

            return `<div class="p-8 text-center text-gray-500">Checkout information not available.</div>`;
        },

        handleFreeCheckout: function(levelId, nonce) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;
            form.innerHTML = '<input type="hidden" name="action" value="orabooks_free_checkout"><input type="hidden" name="level_id" value="' + levelId + '"><input type="hidden" name="orabooks_nonce" value="' + (nonce || '') + '">';
            document.body.appendChild(form);
            form.submit();
        },

        getPaymentNonce: async function() {
            const btn = document.getElementById('complete-purchase-btn');
            const fromBtn = btn && btn.dataset.nonce ? btn.dataset.nonce : '';
            if (fromBtn) {
                return fromBtn;
            }
            if (wpfdVars.paymentNonce) {
                return wpfdVars.paymentNonce;
            }
            try {
                const res = await fetch(wpfdVars.ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    credentials: 'same-origin',
                    body: new URLSearchParams({ action: 'orabooks_payment_nonce' })
                });
                const json = await res.json();
                if (json.success && json.data && json.data.nonce) {
                    if (btn) {
                        btn.dataset.nonce = json.data.nonce;
                    }
                    wpfdVars.paymentNonce = json.data.nonce;
                    return json.data.nonce;
                }
            } catch (e) {
                console.error('Failed to refresh payment nonce', e);
            }
            return '';
        },

        handlePaidCheckout: async function(levelId) {
            const btn = document.getElementById('complete-purchase-btn');
            if (!btn) return;

            const selectedGateway = document.querySelector('input[name="payment_gateway"]:checked');
            if (!selectedGateway) {
                alert('Please select a payment method');
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Processing Payment...';

            const paymentNonce = await this.getPaymentNonce();
            if (!paymentNonce) {
                alert('Payment Error: Unable to verify security token. Please refresh the page and try again.');
                btn.disabled = false;
                btn.textContent = btn.getAttribute('data-original-label') || 'Complete Purchase';
                return;
            }

            fetch(wpfdVars.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: new URLSearchParams({
                    action: 'orabooks_process_payment',
                    level_id: levelId,
                    gateway: selectedGateway.value,
                    nonce: paymentNonce
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.success && response.data?.redirect_url) {
                    window.location.href = response.data.redirect_url;
                } else if (response.success) {
                    alert('Payment initiated successfully. Please check your payment provider.');
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    const msg = typeof response.data === 'string' ? response.data : (response.data?.message || 'Unknown error');
                    alert('Payment Error: ' + msg);
                    btn.disabled = false;
                    btn.textContent = btn.getAttribute('data-original-label') || 'Complete Purchase';
                }
            })
            .catch(() => {
                alert('Network/Server Error. Please try again.');
                btn.disabled = false;
                btn.textContent = btn.getAttribute('data-original-label') || 'Complete Purchase';
            });
        },

        renderAddons: function(data) {
            const features = data.features || [];
            if (features.length === 0) {
                return `<div class="p-8 text-center text-gray-500">No addons available</div>`;
            }

            let html = `
                <div class="space-y-6">
                    <div class="bg-gradient-to-r from-primary-600 to-indigo-700 rounded-3xl p-8 text-white shadow-premium relative overflow-hidden">
                        <div class="relative z-10">
                            <h1 class="text-3xl font-bold mb-2">Your Addons</h1>
                            <p class="text-primary-100 text-lg">Manage and access your installed addons.</p>
                        </div>
                        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            `;

            const categoryColors = {
                'Accounting': 'bg-blue-50 text-blue-600',
                'Inventory': 'bg-green-50 text-green-600',
                'Content': 'bg-purple-50 text-purple-600',
                'Administration': 'bg-orange-50 text-orange-600',
                'Media': 'bg-pink-50 text-pink-600',
                'Membership': 'bg-primary-50 text-primary-600',
                'General': 'bg-gray-50 text-gray-600'
            };

            const featureIcons = {
                'calculator': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="9" y1="6" x2="15" y2="6"></line><line x1="9" y1="10" x2="15" y2="10"></line><line x1="9" y1="14" x2="15" y2="14"></line><line x1="9" y1="18" x2="15" y2="18"></line></svg>',
                'package': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>',
                'archive': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8"></path><path d="M1 3h22v5H1z"></path><line x1="10" y1="12" x2="14" y2="12"></line></svg>',
                'settings': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M12 1v6m0 6v6m4.22-13.22l4.24 4.24M1.54 1.54l4.24 4.24M20.46 20.46l-4.24-4.24M1.54 20.46l4.24-4.24"></path></svg>',
                'users': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
                'filetext': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14,2 14,8 20,8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10,9 9,9 8,9"></polyline></svg>',
                'database': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>',
                'globe': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>',
                'shield': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>',
                'zap': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13,2 3,14 12,14 11,22 21,10 12,10 13,2"></polygon></svg>',
            };

            features.forEach(feature => {
                const colorClass = categoryColors[feature.category] || 'bg-primary-50 text-primary-600';
                const iconSvg = featureIcons[feature.icon] || '';
                const iconHtml = iconSvg
                    ? iconSvg
                    : (feature.icon && feature.icon.length <= 2
                        ? `<span class="text-2xl">${feature.icon}</span>`
                        : `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>`);
                const safeUrl = (feature.url || '#').replace(/'/g, "%27");

                html += `
                    <div onclick="window.location.href='${safeUrl}'" class="bg-white rounded-3xl p-6 shadow-premium border border-gray-100 hover:shadow-premium-hover transition duration-300 cursor-pointer group">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-14 h-14 ${colorClass} rounded-2xl flex items-center justify-center group-hover:scale-110 transition duration-300">
                                ${iconHtml}
                            </div>
                            <div>
                                <span class="text-xs font-bold uppercase tracking-wider text-gray-400">${feature.category || 'Addon'}</span>
                                <h3 class="text-lg font-bold text-gray-900">${feature.name}</h3>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 leading-relaxed">${feature.description || 'No description available'}</p>
                        <div class="mt-4 flex items-center justify-between">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">
                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5"></span>
                                ${feature.status || 'Active'}
                            </span>
                            <span class="text-sm font-bold text-primary-600 group-hover:text-primary-700 transition">Open &rarr;</span>
                        </div>
                    </div>
                `;
            });

            html += `</div></div>`;
            return html;
        },

        renderUsers: function(data) {
            const users = data.items || data;
            let html = `<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">`;
            users.forEach(user => {
                html += `
                    <div class="bg-white p-6 rounded-3xl shadow-premium border border-gray-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-primary-100 text-primary-600 rounded-2xl flex items-center justify-center font-bold text-lg">${user.display_name.charAt(0)}</div>
                        <div class="min-w-0">
                            <div class="font-bold text-gray-900 truncate">${user.display_name}</div>
                            <div class="text-xs text-gray-500 truncate">${user.email}</div>
                            <div class="mt-1"><span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-[10px] font-bold uppercase">${user.role}</span></div>
                        </div>
                    </div>
                `;
            });
            html += `</div>`;
            return html;
        },

        renderStatCard: function(label, value, icon, color) {
            let html = `<div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4 card-hover">`;
            html += `<div class="w-12 h-12 rounded-xl bg-${color}-50 flex items-center justify-center text-${color}-500 text-xl">${icon}</div>`;
            html += `<div><div class="text-2xl font-bold text-gray-900" data-stat="${label.toLowerCase()}">${value}</div><div class="text-sm text-gray-500">${label}</div></div>`;
            html += `</div>`;
            return html;
        },

        renderSettings: function(data) {
            this.settingsData = data;
            const s = data || {};
            const general = s.general || {};

            let html = `
                <div class="space-y-6">
                    <!-- Settings Header -->
                    <div class="bg-gradient-to-r from-primary-600 to-indigo-700 rounded-3xl p-8 text-white shadow-premium relative overflow-hidden">
                        <div class="relative z-10">
                            <h1 class="text-3xl font-bold mb-2">Site Settings</h1>
                            <p class="text-primary-100">Configure your WordPress site preferences</p>
                        </div>
                        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
                    </div>

                    <!-- Simplified Settings -->
                    <div class="bg-white rounded-3xl shadow-premium border border-gray-100 overflow-hidden">
                        <div class="p-6 md:p-8">
                            <div class="max-w-2xl space-y-8">
                                
                                <!-- Basic Site Information -->
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 pb-4 border-b border-gray-100">Site Information</h3>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-bold text-gray-700 mb-2">Site Title</label>
                                            <input type="text" id="setting-site-title" value="${this.escapeHtml(general.site_title || '')}" 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-bold text-gray-700 mb-2">Tagline</label>
                                            <input type="text" id="setting-tagline" value="${this.escapeHtml(general.tagline || '')}" 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                        </div>
                                    </div>

                                    <div class="mt-6">
                                        <label class="block text-sm font-bold text-gray-700 mb-2">Admin Email</label>
                                        <input type="email" id="setting-admin-email" value="${this.escapeHtml(general.admin_email || '')}" 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                        <p class="text-xs text-gray-500 mt-1">This address is used for admin purposes.</p>
                                    </div>
                                </div>

                                <!-- User Settings -->
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 pb-4 border-b border-gray-100">User Settings</h3>
                                    
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox" id="setting-users-can-register" ${general.users_can_register ? 'checked' : ''} 
                                            class="w-5 h-5 text-primary-600 rounded border-gray-300 focus:ring-primary-500">
                                        <label for="setting-users-can-register" class="text-sm text-gray-700">
                                            <span class="font-bold">Anyone can register</span>
                                            <p class="text-gray-500">Check this box if you want to allow user registration</p>
                                        </label>
                                    </div>

                                    <div class="mt-6">
                                        <label class="block text-sm font-bold text-gray-700 mb-2">New User Default Role</label>
                                        <select id="setting-default-role" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                            ${(general.role_options || [{value:'subscriber',label:'Subscriber'},{value:'contributor',label:'Contributor'},{value:'author',label:'Author'},{value:'editor',label:'Editor'},{value:'administrator',label:'Administrator'}]).map(role => 
                                                `<option value="${this.escapeHtml(role.value)}" ${role.value === general.default_role ? 'selected' : ''}>${this.escapeHtml(role.label)}</option>`
                                            ).join('')}
                                        </select>
                                    </div>
                                </div>

                        <!-- Save Button -->
                                <div class="flex items-center justify-between pt-6 border-t border-gray-100">
                                    <span id="settings-save-status" class="text-sm text-gray-500"></span>
                                    <button onclick="Dashboard.saveSettings()" class="px-6 py-2.5 bg-primary-600 text-white rounded-xl font-bold hover:bg-primary-700 transition flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        Save Changes
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            setTimeout(() => this.setupSettingsInteractions(), 0);
            return html;
        },

        switchSettingsTab: function(tab) {
            $('.settings-tab').removeClass('text-primary-600 border-b-2 border-primary-600').addClass('text-gray-500');
            $(`[data-tab="${tab}"]`).addClass('text-primary-600 border-b-2 border-primary-600').removeClass('text-gray-500');
            $('.settings-panel').addClass('hidden');
            $(`#tab-${tab}`).removeClass('hidden');
        },

        setupSettingsInteractions: function() {
            // Update custom permalink radio when typing in custom field
            $('#setting-permalink-custom').on('input', function() {
                $('#permalink-custom').prop('checked', true);
            });
        },

        saveSettings: async function() {
            const $btn = $('button[onclick="Dashboard.saveSettings()"]');
            const $status = $('#settings-save-status');
            
            $btn.prop('disabled', true).html('<svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Saving...');

            const settings = {
                general: {
                    site_title: $('#setting-site-title').val(),
                    tagline: $('#setting-tagline').val(),
                    admin_email: $('#setting-admin-email').val(),
                    timezone: $('#setting-timezone').val(),
                    site_language: $('#setting-language').val(),
                    date_format: $('#setting-date-format').val(),
                    time_format: $('#setting-time-format').val(),
                    start_of_week: $('#setting-start-of-week').val(),
                    users_can_register: $('#setting-users-can-register').prop('checked') ? 1 : 0,
                    default_role: $('#setting-default-role').val()
                },
                reading: {
                    show_on_front: $('input[name="show_on_front"]:checked').val(),
                    page_on_front: $('#setting-page-on-front').val(),
                    page_for_posts: $('#setting-page-for-posts').val(),
                    posts_per_page: $('#setting-posts-per-page').val(),
                    posts_per_rss: $('#setting-posts-per-rss').val(),
                    rss_use_excerpt: $('input[name="rss_use_excerpt"]:checked').val(),
                    blog_public: $('#setting-blog-public').prop('checked') ? 0 : 1
                },
                writing: {
                    default_category: $('#setting-default-category').val(),
                    default_post_format: $('#setting-default-post-format').val(),
                    use_smilies: $('#setting-use-smilies').prop('checked') ? 1 : 0
                },
                media: {
                    thumbnail_size_w: $('#setting-thumbnail-w').val(),
                    thumbnail_size_h: $('#setting-thumbnail-h').val(),
                    thumbnail_crop: $('#setting-thumbnail-crop').prop('checked') ? 1 : 0,
                    medium_size_w: $('#setting-medium-w').val(),
                    medium_size_h: $('#setting-medium-h').val(),
                    large_size_w: $('#setting-large-w').val(),
                    large_size_h: $('#setting-large-h').val(),
                    uploads_use_yearmonth_folders: $('#setting-yearmonth-folders').prop('checked') ? 1 : 0
                },
                discussion: {
                    default_comment_status: $('#setting-comment-status').prop('checked') ? 'open' : 'closed',
                    default_ping_status: $('#setting-ping-status').prop('checked') ? 'open' : 'closed',
                    comment_registration: $('#setting-comment-registration').prop('checked') ? 1 : 0,
                    close_comments_for_old_posts: $('#setting-close-comments').prop('checked') ? 1 : 0,
                    close_comments_days_old: $('#setting-close-days').val(),
                    thread_comments: $('#setting-thread-comments').prop('checked') ? 1 : 0,
                    thread_comments_depth: $('#setting-thread-depth').val(),
                    page_comments: $('#setting-page-comments').prop('checked') ? 1 : 0,
                    comments_per_page: $('#setting-comments-per-page').val(),
                    comment_moderation: $('#setting-comment-moderation').prop('checked') ? 1 : 0,
                    comment_previously_approved: $('#setting-comment-approved').prop('checked') ? 1 : 0
                },
                permalinks: {
                    permalink_structure: permalinkStructure,
                    category_base: $('#setting-category-base').val(),
                    tag_base: $('#setting-tag-base').val()
                }
            };

            try {
                const response = await fetch(`${wpfdVars.restUrl}/site-settings`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpfdVars.restNonce
                    },
                    body: JSON.stringify(settings)
                });
                
                if (response.ok) {
                    $status.text('Settings saved successfully!').addClass('text-green-600').removeClass('text-red-500');
                    setTimeout(() => $status.text(''), 3000);
                } else {
                    const error = await response.json();
                    $status.text(error.message || 'Error saving settings').addClass('text-red-500').removeClass('text-green-600');
                }
            } catch (err) {
                console.error('Save error:', err);
                $status.text('Error saving settings. Please try again.').addClass('text-red-500').removeClass('text-green-600');
            } finally {
                $btn.prop('disabled', false).html('<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Save Changes');
            }
        },

        renderProfile: function(data) {
            const user = data || {};
            const firstName = user.first_name || '';
            const lastName = user.last_name || '';
            const fullName = `${firstName} ${lastName}`.trim() || user.display_name || 'User';
            const hasAvatar = !!user.avatar_url;
            
            let html = `
                <div class="space-y-6">
                    <!-- Profile Header -->
                    <div class="bg-gradient-to-r from-primary-600 to-indigo-700 rounded-3xl p-8 text-white shadow-premium relative overflow-hidden">
                        <div class="relative z-10 flex items-center gap-6">
                            <div class="relative">
                                <div id="profile-avatar-preview" class="w-24 h-24 rounded-2xl ${hasAvatar ? '' : 'bg-white/20'} flex items-center justify-center text-4xl font-bold backdrop-blur-sm border border-white/30 overflow-hidden">
                                    ${hasAvatar ? `<img src="${user.avatar_url}" class="w-full h-full object-cover">` : fullName.charAt(0).toUpperCase()}
                                </div>
                                <button onclick="Dashboard.openAvatarUpload()" class="absolute -bottom-2 -right-2 w-8 h-8 bg-white text-primary-600 rounded-full flex items-center justify-center shadow-lg hover:bg-gray-100 transition" title="Change Photo">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                </button>
                            </div>
                            <div>
                                <h1 class="text-3xl font-bold mb-1">${this.escapeHtml(fullName)}</h1>
                                <p class="text-primary-100">${this.escapeHtml(user.username || '')}</p>
                                <div class="flex items-center gap-2 mt-2">
                                    <span class="px-3 py-1 rounded-full bg-white/20 text-sm font-medium backdrop-blur-sm">${this.escapeHtml(user.roles?.[0]?.charAt(0).toUpperCase() + user.roles?.[0]?.slice(1) || 'User')}</span>
                                </div>
                            </div>
                        </div>
                        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
                    </div>

                    
                    <!-- Upload Progress -->
                    <div id="avatar-upload-progress" class="hidden bg-white rounded-2xl p-4 shadow-premium border border-gray-100">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-bold text-gray-700">Uploading avatar...</span>
                            <span id="avatar-upload-status" class="text-xs text-gray-500">0%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div id="avatar-upload-bar" class="bg-primary-600 h-2 rounded-full transition-all" style="width: 0%"></div>
                        </div>
                    </div>

                    <!-- Profile Form -->
                    <div class="bg-white rounded-3xl shadow-premium border border-gray-100 overflow-hidden">
                        <div class="p-6 md:p-8">
                            <div class="max-w-2xl space-y-8">
                                
                                <!-- Profile Photo Section -->
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 pb-4 border-b border-gray-100 mb-6">Profile Photo</h3>
                                    <div class="flex items-center gap-6">
                                        <div id="profile-avatar-preview-large" class="w-20 h-20 rounded-2xl ${hasAvatar ? '' : 'bg-gray-100'} flex items-center justify-center text-2xl font-bold border border-gray-200 overflow-hidden">
                                            ${hasAvatar ? `<img src="${user.avatar_url}" class="w-full h-full object-cover">` : fullName.charAt(0).toUpperCase()}
                                        </div>
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-3">
                                                <button onclick="Dashboard.openAvatarUpload()" class="px-4 py-2 bg-primary-600 text-white rounded-xl font-bold hover:bg-primary-700 transition flex items-center gap-2">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                                    Upload Photo
                                                </button>
                                                ${hasAvatar ? `
                                                <button onclick="Dashboard.removeAvatar()" class="px-4 py-2 bg-red-100 text-red-700 rounded-xl font-bold hover:bg-red-200 transition">
                                                    Remove
                                                </button>
                                                ` : ''}
                                            </div>
                                            <p class="text-xs text-gray-500">Recommended: Square image, at least 200x200 pixels</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Basic Information -->
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 pb-4 border-b border-gray-100 mb-6">Basic Information</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-bold text-gray-700 mb-2">First Name</label>
                                            <input type="text" id="profile-first-name" value="${this.escapeHtml(firstName)}" 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-bold text-gray-700 mb-2">Last Name</label>
                                            <input type="text" id="profile-last-name" value="${this.escapeHtml(lastName)}" 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                        </div>
                                    </div>
                                    <div class="mt-6">
                                        <label class="block text-sm font-bold text-gray-700 mb-2">Display Name</label>
                                        <input type="text" id="profile-display-name" value="${this.escapeHtml(user.display_name || '')}" 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    </div>
                                    <div class="mt-6">
                                        <label class="block text-sm font-bold text-gray-700 mb-2">Username</label>
                                        <input type="text" value="${this.escapeHtml(user.username || '')}" disabled
                                            class="w-full px-4 py-2 bg-gray-100 border border-gray-200 rounded-xl text-gray-500 cursor-not-allowed">
                                        <p class="text-xs text-gray-500 mt-1">Username cannot be changed</p>
                                    </div>
                                </div>

                                <!-- Contact Information -->
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 pb-4 border-b border-gray-100 mb-6">Contact Information</h3>
                                    <div class="space-y-6">
                                        <div>
                                            <label class="block text-sm font-bold text-gray-700 mb-2">Email Address</label>
                                            <input type="email" id="profile-email" value="${this.escapeHtml(user.email || '')}" 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-bold text-gray-700 mb-2">Phone Number</label>
                                            <input type="tel" id="profile-phone" value="${this.escapeHtml(user.phone || '')}" 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                                placeholder="+1 (555) 123-4567">
                                        </div>
                                    </div>
                                </div>

                                <!-- Professional Information -->
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 pb-4 border-b border-gray-100 mb-6">Professional Information</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-bold text-gray-700 mb-2">Designation</label>
                                            <input type="text" id="profile-designation" value="${this.escapeHtml(user.designation || '')}" 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                                placeholder="e.g. Senior Developer">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-bold text-gray-700 mb-2">Department</label>
                                            <input type="text" id="profile-department" value="${this.escapeHtml(user.department || '')}" 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                                placeholder="e.g. Engineering">
                                        </div>
                                    </div>
                                </div>

                                <!-- About/Bio -->
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 pb-4 border-b border-gray-100 mb-6">About</h3>
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-2">Bio</label>
                                        <textarea id="profile-bio" rows="4" 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 resize-none"
                                            placeholder="Tell us a little about yourself...">${this.escapeHtml(user.description || '')}</textarea>
                                        <p class="text-xs text-gray-500 mt-1">Share a brief description about yourself</p>
                                    </div>
                                </div>

                                <!-- Password Change -->
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 pb-4 border-b border-gray-100 mb-6">Change Password</h3>
                                    <div class="space-y-6">
                                        <div>
                                            <label class="block text-sm font-bold text-gray-700 mb-2">New Password</label>
                                            <input type="password" id="profile-password" 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                                placeholder="Leave blank to keep current password">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-bold text-gray-700 mb-2">Confirm New Password</label>
                                            <input type="password" id="profile-password-confirm" 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                                placeholder="Leave blank to keep current password">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <div class="p-6 bg-gray-50 border-t border-gray-100">
                            <div class="max-w-2xl flex items-center justify-between">
                                <span id="profile-save-status" class="text-sm text-gray-500"></span>
                                <button onclick="Dashboard.saveProfile()" class="px-6 py-2.5 bg-primary-600 text-white rounded-xl font-bold hover:bg-primary-700 transition flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    Save Profile
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            return html;
        },

        renderSecurity: function(data) {
            // Check if TFA is available
            if (!data.tfa_available || !data.html) {
                let statusHtml = '';
                if (!data.tfa_available) {
                    statusHtml = `
                        <div class="text-center py-8">
                            <div class="w-20 h-20 bg-gray-100 text-gray-400 rounded-3xl flex items-center justify-center mx-auto mb-4">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H10m9.364-7.364A9 9 0 1112 3a9 9 0 019.364 9.364z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">Two-Factor Authentication</h3>
                            <p class="text-gray-500 max-w-md mx-auto">Two-factor authentication is not available for your account. Please contact your site administrator.</p>
                        </div>
                    `;
                } else if (!data.tfa_enabled_for_user) {
                    statusHtml = `
                        <div class="text-center py-8">
                            <div class="w-20 h-20 bg-yellow-50 text-yellow-600 rounded-3xl flex items-center justify-center mx-auto mb-4">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">Two-Factor Authentication</h3>
                            <p class="text-gray-500 max-w-md mx-auto">Two-factor authentication is not enabled for your user role. Please contact your site administrator.</p>
                        </div>
                    `;
                }
                return `
                    <div class="space-y-6">
                        <div class="bg-gradient-to-r from-primary-600 to-indigo-700 rounded-3xl p-8 text-white shadow-premium relative overflow-hidden">
                            <div class="relative z-10">
                                <h1 class="text-3xl font-bold mb-2">Security Settings</h1>
                                <p class="text-primary-100 text-lg">Manage your account security and two-factor authentication.</p>
                            </div>
                            <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
                        </div>
                        <div class="bg-white rounded-3xl p-8 shadow-premium border border-gray-100">
                            ${statusHtml}
                        </div>
                    </div>
                `;
            }

            // TFA is available and enabled - render the HTML from the shortcode
            // We need to ensure TFA frontend scripts are loaded
            setTimeout(() => {
                this.reinitializeTfaScripts();
            }, 100);

            return `
                <div class="space-y-6">
                    <div class="bg-gradient-to-r from-primary-600 to-indigo-700 rounded-3xl p-8 text-white shadow-premium relative overflow-hidden">
                        <div class="relative z-10">
                            <h1 class="text-3xl font-bold mb-2">Security Settings</h1>
                            <p class="text-primary-100 text-lg">Manage your two-factor authentication settings.</p>
                        </div>
                        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
                    </div>
                    <div id="tfa-settings-container" class="bg-white rounded-3xl p-6 md:p-8 shadow-premium border border-gray-100 tfa-dashboard-wrapper">
                        ${data.html}
                    </div>
                </div>
            `;
        },

        reinitializeTfaScripts: function() {
            // Re-run TFA script initialization after HTML is injected
            if (typeof simba_tfa_frontend !== 'undefined' && typeof simba_tfa_frontend.ajax_url !== 'undefined') {
                console.log('TFA frontend scripts detected, re-initializing...');
                
                // Re-bind save button for TFA settings
                const saveBtn = document.querySelector('.simbatfa_settings_save');
                if (saveBtn) {
                    // Clone and replace to remove old event listeners
                    const newBtn = saveBtn.cloneNode(true);
                    saveBtn.parentNode.replaceChild(newBtn, saveBtn);
                    
                    newBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const form = this.closest('.tfa_settings_form') || document.querySelector('.tfa_settings_form');
                        if (form) {
                            const formData = new FormData(form);
                            const settings = new URLSearchParams(formData).toString();
                            
                            // Show saving indicator
                            const originalText = this.textContent;
                            this.textContent = 'Saving...';
                            this.disabled = true;
                            
                            fetch(simba_tfa_frontend.ajax_url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: new URLSearchParams({
                                    action: 'tfa_frontend',
                                    subaction: 'savesettings',
                                    nonce: simba_tfa_frontend.nonce,
                                    settings: settings
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                this.textContent = 'Saved!';
                                setTimeout(() => {
                                    this.textContent = originalText;
                                    this.disabled = false;
                                }, 2000);
                                
                                if (data.qr) {
                                    const qrImg = document.querySelector('.tfa_qr_code img');
                                    if (qrImg) qrImg.src = data.qr;
                                }
                            })
                            .catch(error => {
                                console.error('TFA save error:', error);
                                this.textContent = 'Error';
                                setTimeout(() => {
                                    this.textContent = originalText;
                                    this.disabled = false;
                                }, 3000);
                            });
                        }
                    });
                }
            }
        },

        saveProfile: async function() {
            const $btn = $('button[onclick="Dashboard.saveProfile()"]');
            const $status = $('#profile-save-status');
            
            // Check password match if provided
            const password = $('#profile-password').val();
            const passwordConfirm = $('#profile-password-confirm').val();
            if (password && password !== passwordConfirm) {
                $status.text('Passwords do not match').addClass('text-red-500').removeClass('text-green-600');
                return;
            }
            
            $btn.prop('disabled', true).html('<svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Saving...');

            const profile = {
                first_name: $('#profile-first-name').val(),
                last_name: $('#profile-last-name').val(),
                display_name: $('#profile-display-name').val(),
                email: $('#profile-email').val(),
                phone: $('#profile-phone').val(),
                designation: $('#profile-designation').val(),
                department: $('#profile-department').val(),
                description: $('#profile-bio').val()
            };
            
            if (password) {
                profile.password = password;
            }

            try {
                const response = await fetch(`${wpfdVars.restUrl}/users/me`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpfdVars.restNonce
                    },
                    body: JSON.stringify(profile)
                });
                
                if (response.ok) {
                    const result = await response.json();
                    $status.text('Profile saved successfully!').addClass('text-green-600').removeClass('text-red-500');
                    
                    // Update sidebar display name if changed
                    if (result.user && result.user.display_name) {
                        $('.nav-btn[data-section="profile"] .font-semibold').text(result.user.display_name);
                    }
                    
                    setTimeout(() => $status.text(''), 3000);
                } else {
                    const error = await response.json();
                    $status.text(error.message || 'Error saving profile').addClass('text-red-500').removeClass('text-green-600');
                }
            } catch (err) {
                console.error('Save error:', err);
                $status.text('Error saving profile. Please try again.').addClass('text-red-500').removeClass('text-green-600');
            } finally {
                $btn.prop('disabled', false).html('<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Save Profile');
            }
        },

        openAvatarUpload: function() {
            console.log('Opening avatar upload dialog');
            
            // Check if file input exists, create it if not
            let fileInput = document.getElementById('profile-avatar-input');
            if (!fileInput) {
                console.log('Creating file input element');
                fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.id = 'profile-avatar-input';
                fileInput.accept = 'image/*';
                fileInput.style.display = 'none';
                fileInput.setAttribute('capture', 'user');
                
                // Add change event listener
                fileInput.addEventListener('change', function(e) {
                    console.log('File input change triggered');
                    const file = e.target.files[0];
                    if (file) {
                        Dashboard.uploadAvatar(file);
                    }
                });
                
                // Add to body
                document.body.appendChild(fileInput);
            } else {
                console.log('File input already exists');
                // Reset file input to allow selecting the same file again
                fileInput.value = '';
            }
            
            // Trigger click with multiple fallbacks
            try {
                console.log('Attempting to trigger file input click');
                fileInput.click();
            } catch (err) {
                console.log('Primary click failed, trying fallback');
                try {
                    // Fallback for mobile browsers
                    const event = new MouseEvent('click', {
                        bubbles: true,
                        cancelable: true,
                        view: window
                    });
                    fileInput.dispatchEvent(event);
                } catch (err2) {
                    console.error('All click methods failed:', err2);
                    // Last resort - focus and trigger
                    fileInput.focus();
                    fileInput.click();
                }
            }
        },

        uploadAvatar: async function(file) {
            console.log('Avatar upload started with file:', file);
            
            if (!file) {
                console.error('No file provided for upload');
                return;
            }
            
            // Validate file type and size
            if (!file.type.startsWith('image/')) {
                console.error('Invalid file type:', file.type);
                alert('Please select an image file.');
                return;
            }
            
            if (file.size > 5 * 1024 * 1024) { // 5MB limit
                console.error('File too large:', file.size);
                alert('File size must be less than 5MB.');
                return;
            }
            
            const $progress = $('#avatar-upload-progress');
            const $bar = $('#avatar-upload-bar');
            const $status = $('#avatar-upload-status');
            
            $progress.removeClass('hidden');
            $bar.css('width', '0%');
            $status.text('0%');
            
            const formData = new FormData();
            formData.append('file', file);

            try {
                // Simulate progress
                let progress = 0;
                const progressInterval = setInterval(() => {
                    progress = Math.min(progress + 10, 80);
                    $bar.css('width', progress + '%');
                    $status.text(progress + '%');
                }, 100);

                const response = await fetch(`${wpfdVars.restUrl}/users/me/avatar`, {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': wpfdVars.restNonce },
                    body: formData,
                    credentials: 'same-origin'
                });
                
                clearInterval(progressInterval);

                if (response.ok) {
                    const result = await response.json();
                    $bar.css('width', '100%');
                    $status.text('100%');
                    
                    // Update avatar previews
                    const fullName = ($('#profile-first-name').val() + ' ' + $('#profile-last-name').val()).trim() || $('#profile-display-name').val() || 'User';
                    const newAvatarHtml = `<img src="${result.avatar_url}" class="w-full h-full object-cover">`;
                    $('#profile-avatar-preview').html(newAvatarHtml).removeClass('bg-white/20');
                    $('#profile-avatar-preview-large').html(newAvatarHtml).removeClass('bg-gray-100');
                    
                    // Update sidebar avatar - handle both desktop and mobile
                    $('.nav-btn[data-section="profile"] .rounded-full, .nav-btn[data-section="profile"] .overflow-hidden').each(function() {
                        $(this).html(`<img src="${result.avatar_url}" class="w-full h-full object-cover">`);
                    });
                    
                    setTimeout(() => {
                        $progress.addClass('hidden');
                        // Reload profile to update UI with remove button
                        this.loadSection('profile');
                    }, 500);
                } else {
                    const error = await response.json();
                    $progress.addClass('hidden');
                    alert(error.message || 'Error uploading avatar. Please try again.');
                }
            } catch (err) {
                console.error('Avatar upload error:', err);
                $progress.addClass('hidden');
                alert('Error uploading avatar. Please try again.');
            }
        },

        removeAvatar: async function() {
            if (!confirm('Are you sure you want to remove your profile photo?')) return;
            
            try {
                const response = await fetch(`${wpfdVars.restUrl}/users/me/avatar`, {
                    method: 'DELETE',
                    headers: { 'X-WP-Nonce': wpfdVars.restNonce }
                });
                
                if (response.ok) {
                    // Reload profile to update UI
                    this.loadSection('profile');
                } else {
                    const error = await response.json();
                    alert(error.message || 'Error removing avatar. Please try again.');
                }
            } catch (err) {
                console.error('Avatar remove error:', err);
                alert('Error removing avatar. Please try again.');
            }
        },

        setupSearch: function() {
            const $searchInput = $('#search-container');
            
            // Click search container to open modal
            $searchInput.on('click', (e) => {
                e.preventDefault();
                this.showSearchModal();
            });
            
            // Global keyboard shortcut (Ctrl+K or Cmd+K)
            $(document).on('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    this.showSearchModal();
                }
            });
        },

        getItemIcon: function(type, item) {
            const icons = {
                post: '📝',
                page: '📄',
                user: '👤',
                media: '🖼️'
            };
            return icons[type] || '📄';
        },

getOrCreateSearchModal: function() {
            let $modal = $('#search-modal-container');
            if ($modal.length === 0) {
                $modal = $(`
                    <div id="search-modal-container" class="fixed inset-0 z-50 hidden items-start justify-center pt-20 bg-black/50 backdrop-blur-sm">
                        <div id="search-modal-content" class="bg-white rounded-3xl shadow-2xl w-[90%] max-w-2xl max-h-[70vh] flex flex-col overflow-hidden scale-95 opacity-0 transition-all duration-200">
                            <!-- Search Input Header -->
                            <div class="p-4 border-b border-gray-100 flex items-center gap-3">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                <input type="text" id="search-modal-input" placeholder="Search everything..." 
                                    class="flex-1 text-lg border-none outline-none placeholder-gray-400">
                                <button class="search-close-btn p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                                <div class="hidden sm:flex items-center gap-1 text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded">
                                    <kbd class="bg-white border border-gray-300 rounded px-1">K</kbd>
                                </div>
                            </div>
                            <!-- Results -->
                            <div id="search-modal-results" class="flex-1 overflow-y-auto">
                                <div class="p-8 text-center text-gray-400">
                                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    <p>Type to search posts, pages, media, and users...</p>
                                </div>
                            </div>
                            <!-- Footer -->
                            <div class="p-3 border-t border-gray-100 bg-gray-50 flex items-center justify-between text-xs text-gray-500">
                                <span id="search-modal-count"></span>
                                <div class="flex items-center gap-3">
                                    <span class="flex items-center gap-1"><kbd class="bg-white border border-gray-300 rounded px-1">↑</kbd> <kbd class="bg-white border border-gray-300 rounded px-1">↓</kbd> to navigate</span>
                                    <span class="flex items-center gap-1"><kbd class="bg-white border border-gray-300 rounded px-1">↵</kbd> to select</span>
                                    <span class="flex items-center gap-1"><kbd class="bg-white border border-gray-300 rounded px-1">Esc</kbd> to close</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
                $('body').append($modal);
                
                // Event delegation for proper event handling
                $modal.on('click', (e) => {
                    if (e.target === $modal[0]) {
                        this.hideSearchResults();
                    }
                });
                
                $modal.find('.search-close-btn').on('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.hideSearchResults();
                });
                
                $modal.find('#search-modal-input').on('input', (e) => {
                    this.handleModalSearch(e.target.value);
                });
                
                // Event delegation for search result items
                $modal.on('click', '.search-result-item', (e) => {
                    e.preventDefault();
                    const url = $(e.currentTarget).data('url');
                    if (url && url !== '#') {
                        window.location.href = url;
                    }
                });
                
                // Keyboard navigation for modal
                $modal.on('keydown', (e) => {
                    if (e.key === 'Escape') {
                        this.hideSearchResults();
                    } else if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        this.navigateSearchResults('down');
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        this.navigateSearchResults('up');
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        this.selectSearchResult();
                    }
                });
            }
            return $modal;
        },

        handleModalSearch: function(query) {
            clearTimeout(this.searchTimeout);
            if (query.length > 1) {
                this.searchTimeout = setTimeout(() => this.performSearch(query), 300);
            } else {
                $('#search-modal-results').html(`
                    <div class="p-8 text-center text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <p>Type at least 2 characters to search...</p>
                    </div>
                `);
                $('#search-modal-count').text('');
            }
        },

        performSearch: async function(query) {
            const $results = $('#search-modal-results');
            $results.html(`
                <div class="p-8 space-y-3">
                    ${[1,2,3].map(() => `
                        <div class="flex items-center gap-4 p-3 rounded-xl bg-gray-50 animate-pulse">
                            <div class="w-10 h-10 bg-gray-200 rounded-lg"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                                <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `);

            try {
                const response = await fetch(`${wpfdVars.restUrl}/dashboard/search?q=${encodeURIComponent(query)}&limit=10`, {
                    headers: { 'X-WP-Nonce': wpfdVars.restNonce }
                });
                
                if (!response.ok) throw new Error('Search failed');
                
                const data = await response.json();
                this.displayModalSearchResults(data);
            } catch (error) {
                console.error('Search error:', error);
                $results.html(`
                    <div class="p-8 text-center text-red-500">
                        <svg class="w-12 h-12 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <p>Error loading results. Please try again.</p>
                    </div>
                `);
            }
        },

        displayModalSearchResults: function(data) {
            const $results = $('#search-modal-results');
            const $count = $('#search-modal-count');
            let html = '';
            let totalResults = 0;

            const sections = [
                { title: 'Posts', items: data.posts, type: 'post' },
                { title: 'Pages', items: data.pages, type: 'page' },
                { title: 'Media', items: data.media, type: 'media' },
                { title: 'Users', items: data.users, type: 'user' }
            ];

            sections.forEach(section => {
                if (section.items && section.items.length > 0) {
                    html += `<div class="px-4 py-2 bg-gray-50 text-xs font-bold text-gray-500 uppercase tracking-wider">${section.title}</div>`;
                    section.items.forEach(item => {
                        totalResults++;
                        html += this.renderModalSearchResultItem(item, section.type);
                    });
                }
            });

            if (totalResults === 0) {
                html = `
                    <div class="p-8 text-center text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <p>No results found for "${this.escapeHtml(data.query)}"</p>
                    </div>
                `;
            }

            $results.html(html || '<div class="p-8 text-center text-gray-400">No results found</div>');
            $count.text(totalResults > 0 ? `${totalResults} result${totalResults > 1 ? 's' : ''}` : '');
            this.selectedResultIndex = -1;
        },

        renderModalSearchResultItem: function(item, type) {
            const icon = this.getItemIcon(type, item);
            const status = item.status ? `<span class="px-2 py-0.5 rounded-full text-xs font-bold ${item.status === 'publish' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}">${item.status}</span>` : '';
            const date = item.date ? this.formatDate(item.date) : '';
            const url = this.escapeHtml(item.url || '#');
            const title = this.escapeHtml(item.title || item.name);
            
            return `
                <div class="search-result-item flex items-center gap-4 p-4 hover:bg-gray-50 cursor-pointer border-b border-gray-50 last:border-0" data-url="${url}">
                    <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center text-xl">${icon}</div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-gray-900 truncate">${title}</div>
                        <div class="flex items-center gap-2 text-sm text-gray-500 mt-0.5">
                            ${status}
                            ${date ? `<span>${date}</span>` : ''}
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </div>
            `;
        },

        hideSearchResults: function() {
            const $modal = $('#search-modal-container');
            const $content = $('#search-modal-content');
            if ($modal.length) {
                $content.removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
                setTimeout(() => {
                    $modal.addClass('hidden').removeClass('flex items-start justify-center');
                    // Reset content classes for next show
                    $content.removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
                }, 200);
            }
            // Also hide old search results if they exist
            $('#search-results').removeClass('show');
        },

        showSearchModal: function() {
            const $modal = this.getOrCreateSearchModal();
            const $content = $('#search-modal-content');
            const $input = $('#search-modal-input');
            
            // Show modal with proper classes
            $modal.removeClass('hidden').addClass('flex items-start justify-center');
            
            // Animate in
            setTimeout(() => {
                $content.removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
            }, 10);
            
            // Focus input and clear previous results
            $input.focus().val('');
            this.handleModalSearch('');
        },

        navigateSearchResults: function(direction) {
            const $items = $('#search-modal-results .search-result-item');
            if ($items.length === 0) return;

            $items.removeClass('selected bg-primary-50');
            
            if (direction === 'down') {
                this.selectedResultIndex = (this.selectedResultIndex + 1) % $items.length;
            } else {
                this.selectedResultIndex = (this.selectedResultIndex - 1 + $items.length) % $items.length;
            }

            const $selected = $items.eq(this.selectedResultIndex);
            $selected.addClass('selected bg-primary-50');
            $selected[0].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        },

        selectSearchResult: function() {
            const $selected = $('#search-modal-results .search-result-item.selected');
            if ($selected.length > 0) {
                window.location.href = $selected.data('url');
            }
        },

        formatDate: function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        renderMedia: function(data) {
            const items = data.items || [];
            const total = data.total || items.length;
            const page = data.page || 1;
            const per_page = data.per_page || 20;
            const totalPages = Math.ceil(total / per_page);

            let html = `
                <div class="space-y-6">
                    <!-- Media Header -->
                    <div class="bg-white rounded-3xl p-6 shadow-premium border border-gray-100">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">Media Library</h3>
                                <p class="text-sm text-gray-500 mt-1">${total} items</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <!-- Search -->
                                <div class="relative">
                                    <input type="text" id="media-search" placeholder="Search media..." 
                                        class="w-48 md:w-64 bg-gray-100 border-none rounded-xl py-2.5 pl-10 pr-4 text-sm focus:ring-2 focus:ring-primary-500/20 focus:bg-white transition-all"
                                        value="${this.mediaSearchQuery || ''}">
                                    <svg class="w-4 h-4 text-gray-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                                <!-- Filter -->
                                <select id="media-filter" class="bg-gray-100 border-none rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-primary-500/20 focus:bg-white cursor-pointer">
                                    <option value="">All Types</option>
                                    <option value="image">Images</option>
                                    <option value="video">Videos</option>
                                    <option value="audio">Audio</option>
                                    <option value="application">Documents</option>
                                </select>
                                <!-- Upload Button -->
                                <button onclick="Dashboard.openMediaUpload()" class="px-4 py-2.5 bg-primary-600 text-white rounded-xl font-bold hover:bg-primary-700 transition flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                    Upload
                                </button>
                            </div>
                        </div>

                        <!-- Bulk Actions -->
                        <div id="media-bulk-actions" class="hidden mt-4 pt-4 border-t border-gray-100 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-gray-600"><span id="selected-count">0</span> selected</span>
                                <button onclick="Dashboard.bulkDeleteMedia()" class="px-3 py-1.5 bg-red-100 text-red-700 rounded-lg text-sm font-bold hover:bg-red-200 transition">
                                    Delete Selected
                                </button>
                            </div>
                            <button onclick="Dashboard.clearSelection()" class="text-sm text-gray-500 hover:text-gray-700">Clear selection</button>
                        </div>
                    </div>

                    <!-- Upload Drop Zone -->
                    <div id="media-drop-zone" class="hidden bg-gradient-to-r from-primary-50 to-blue-50 border-2 border-dashed border-primary-300 rounded-3xl p-8 text-center">
                        <div class="space-y-3">
                            <div class="w-16 h-16 bg-primary-100 text-primary-600 rounded-2xl flex items-center justify-center mx-auto">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                            </div>
                            <h4 class="font-bold text-gray-900">Drop files to upload</h4>
                            <p class="text-sm text-gray-500">or click to browse</p>
                            <input type="file" id="media-file-input" multiple accept="image/*,video/*,audio/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.*" class="hidden">
                        </div>
                    </div>

                    <!-- Upload Progress -->
                    <div id="upload-progress" class="hidden bg-white rounded-2xl p-4 shadow-premium border border-gray-100">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-bold text-gray-700">Uploading...</span>
                            <span id="upload-status" class="text-xs text-gray-500">0%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div id="upload-bar" class="bg-primary-600 h-2 rounded-full transition-all" style="width: 0%"></div>
                        </div>
                    </div>

                    <!-- Upload Error Container -->
                    <div id="upload-error" class="hidden"></div>

                    <!-- Media Grid -->
                    ${items.length === 0 ? `
                        <div class="bg-white rounded-3xl p-12 text-center shadow-premium border border-gray-100">
                            <div class="w-20 h-20 bg-gray-100 text-gray-400 rounded-3xl flex items-center justify-center mx-auto mb-4">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                            <h4 class="font-bold text-gray-900 mb-2">No media found</h4>
                            <p class="text-gray-500 text-sm mb-4">Upload some files to get started</p>
                            <button onclick="Dashboard.openMediaUpload()" class="px-4 py-2 bg-primary-600 text-white rounded-lg font-bold hover:bg-primary-700 transition">Upload Files</button>
                        </div>
                    ` : `
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                            ${items.map(item => this.renderMediaItem(item)).join('')}
                        </div>
                    `}

                    <!-- Pagination -->
                    ${totalPages > 1 ? `
                        <div class="flex items-center justify-center gap-2 pt-4">
                            <button onclick="Dashboard.loadMediaPage(${page - 1})" ${page <= 1 ? 'disabled' : ''} 
                                class="px-4 py-2 rounded-xl bg-white border border-gray-200 text-gray-700 font-bold hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition">
                                Previous
                            </button>
                            <div class="flex items-center gap-1">
                                ${this.renderPaginationNumbers(page, totalPages)}
                            </div>
                            <button onclick="Dashboard.loadMediaPage(${page + 1})" ${page >= totalPages ? 'disabled' : ''} 
                                class="px-4 py-2 rounded-xl bg-white border border-gray-200 text-gray-700 font-bold hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition">
                                Next
                            </button>
                        </div>
                    ` : ''}
                </div>
            `;

            // Store data for later use
            this.mediaData = data;
            this.selectedMedia = new Set();

            // Schedule post-render setup
            setTimeout(() => this.setupMediaInteractions(), 0);

            return html;
        },

        renderMediaItem: function(item) {
            const isImage = item.mime && item.mime.startsWith('image/');
            const isVideo = item.mime && item.mime.startsWith('video/');
            const isAudio = item.mime && item.mime.startsWith('audio/');
            
            let icon = '📄';
            if (isImage) icon = '🖼️';
            if (isVideo) icon = '🎬';
            if (isAudio) icon = '🎵';

            return `
                <div class="media-item bg-white rounded-2xl overflow-hidden shadow-premium border border-gray-100 group relative cursor-pointer" 
                     data-id="${item.id}" onclick="Dashboard.selectMediaItem(event, ${item.id})">
                    
                    <!-- Selection Checkbox -->
                    <div class="absolute top-2 left-2 z-10 opacity-0 group-hover:opacity-100 transition">
                        <input type="checkbox" class="media-checkbox w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" 
                               value="${item.id}" onclick="event.stopPropagation()">
                    </div>

                    <!-- Thumbnail -->
                    <div class="aspect-square bg-gray-100 relative overflow-hidden">
                        ${isImage ? `
                            <img src="${item.url}" class="w-full h-full object-cover group-hover:scale-110 transition duration-500" loading="lazy">
                        ` : `
                            <div class="w-full h-full flex items-center justify-center text-4xl bg-gray-50">
                                ${icon}
                            </div>
                        `}
                        
                        <!-- Hover Actions -->
                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2">
                            <button onclick="event.stopPropagation(); Dashboard.viewMedia(${item.id})" 
                                class="p-2 bg-white rounded-full text-gray-900 hover:bg-primary-50 transition" title="View">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            </button>
                            <button onclick="event.stopPropagation(); Dashboard.editMedia(${item.id})" 
                                class="p-2 bg-white rounded-full text-gray-900 hover:bg-primary-50 transition" title="Edit">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </button>
                            <button onclick="event.stopPropagation(); Dashboard.deleteMedia(${item.id})" 
                                class="p-2 bg-white rounded-full text-red-600 hover:bg-red-50 transition" title="Delete">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Info -->
                    <div class="p-3">
                        <div class="text-sm font-medium text-gray-900 truncate">${this.escapeHtml(item.title || 'Untitled')}</div>
                        <div class="text-xs text-gray-500 mt-1 flex items-center justify-between">
                            <span>${item.mime ? item.mime.split('/')[1]?.toUpperCase() || 'FILE' : 'FILE'}</span>
                        </div>
                    </div>
                </div>
            `;
        },

        renderPaginationNumbers: function(currentPage, totalPages) {
            let html = '';
            const maxVisible = 5;
            let start = Math.max(1, currentPage - Math.floor(maxVisible / 2));
            let end = Math.min(totalPages, start + maxVisible - 1);
            
            if (end - start < maxVisible - 1) {
                start = Math.max(1, end - maxVisible + 1);
            }

            if (start > 1) {
                html += `<button onclick="Dashboard.loadMediaPage(1)" class="w-10 h-10 rounded-xl bg-white border border-gray-200 text-gray-700 font-bold hover:bg-gray-50 transition">1</button>`;
                if (start > 2) html += `<span class="px-2 text-gray-400">...</span>`;
            }

            for (let i = start; i <= end; i++) {
                const isActive = i === currentPage;
                html += `<button onclick="Dashboard.loadMediaPage(${i})" 
                    class="w-10 h-10 rounded-xl font-bold transition ${isActive ? 'bg-primary-600 text-white' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50'}">
                    ${i}
                </button>`;
            }

            if (end < totalPages) {
                if (end < totalPages - 1) html += `<span class="px-2 text-gray-400">...</span>`;
                html += `<button onclick="Dashboard.loadMediaPage(${totalPages})" class="w-10 h-10 rounded-xl bg-white border border-gray-200 text-gray-700 font-bold hover:bg-gray-50 transition">${totalPages}</button>`;
            }

            return html;
        },

        setupMediaInteractions: function() {
            // Search
            $('#media-search').on('input', (e) => {
                clearTimeout(this.mediaSearchTimeout);
                this.mediaSearchQuery = e.target.value;
                this.mediaSearchTimeout = setTimeout(() => this.loadMediaPage(1), 300);
            });

            // Filter
            $('#media-filter').on('change', (e) => {
                this.mediaFilter = e.target.value;
                this.loadMediaPage(1);
            });

            // File input change
            $('#media-file-input').on('change', (e) => {
                if (e.target.files.length > 0) {
                    this.uploadMediaFiles(e.target.files);
                }
            });

            // Drag and drop
            const dropZone = $('#media-drop-zone')[0];
            if (dropZone) {
                dropZone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    dropZone.classList.add('border-primary-500', 'bg-primary-100');
                });
                dropZone.addEventListener('dragleave', () => {
                    dropZone.classList.remove('border-primary-500', 'bg-primary-100');
                });
                dropZone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    dropZone.classList.remove('border-primary-500', 'bg-primary-100');
                    if (e.dataTransfer.files.length > 0) {
                        this.uploadMediaFiles(e.dataTransfer.files);
                    }
                });
                dropZone.addEventListener('click', () => $('#media-file-input').click());
            }
        },

        openMediaUpload: function() {
            $('#media-drop-zone').removeClass('hidden');
            $('#media-file-input').click();
        },

        uploadMediaFiles: async function(files) {
            const $progress = $('#upload-progress');
            const $bar = $('#upload-bar');
            const $status = $('#upload-status');
            const $errorContainer = $('#upload-error');
            
            $progress.removeClass('hidden');
            $('#media-drop-zone').addClass('hidden');
            $errorContainer.addClass('hidden');
            
            let uploadedCount = 0;
            let errorCount = 0;
            const errors = [];

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const percent = Math.round((i / files.length) * 100);
                $bar.css('width', percent + '%');
                $status.text(`${i + 1}/${files.length} - Uploading: ${file.name}`);

                // Validate file
                if (!this.validateMediaFile(file)) {
                    errorCount++;
                    errors.push(`${file.name}: Invalid file type or size`);
                    continue;
                }

                const formData = new FormData();
                formData.append('file', file);

                try {
                    const response = await fetch(`${wpfdVars.restUrl}/media`, {
                        method: 'POST',
                        headers: { 'X-WP-Nonce': wpfdVars.restNonce },
                        body: formData
                    });

                    if (response.ok) {
                        const result = await response.json();
                        uploadedCount++;
                        console.log('Upload successful:', result);
                    } else {
                        const error = await response.json();
                        errorCount++;
                        errors.push(`${file.name}: ${error.message || 'Upload failed'}`);
                    }
                } catch (err) {
                    console.error('Upload error:', err);
                    errorCount++;
                    errors.push(`${file.name}: Network error`);
                }
            }

            $bar.css('width', '100%');
            
            if (errorCount > 0) {
                $status.text(`Complete! ${uploadedCount} uploaded, ${errorCount} failed`);
                $errorContainer.removeClass('hidden').html(`
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mt-4">
                        <h4 class="text-red-800 font-medium mb-2">Upload Errors:</h4>
                        <ul class="text-red-700 text-sm space-y-1">
                            ${errors.map(error => `<li>• ${error}</li>`).join('')}
                        </ul>
                    </div>
                `);
            } else {
                $status.text(`Complete! ${uploadedCount} files uploaded successfully`);
            }
            
            setTimeout(() => {
                $progress.addClass('hidden');
                $bar.css('width', '0%');
                $errorContainer.addClass('hidden');
                this.loadMediaPage(1);
            }, 2000);
        },

        validateMediaFile: function(file) {
            // Allowed file types
            const allowedTypes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
                'video/mp4', 'video/webm', 'video/ogg',
                'audio/mp3', 'audio/wav', 'audio/ogg',
                'application/pdf', 'text/plain', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ];
            
            // Max file size (50MB)
            const maxSize = 50 * 1024 * 1024;
            
            return allowedTypes.includes(file.type) && file.size <= maxSize;
        },

        loadMediaPage: function(page) {
            const params = { page: page, per_page: 20 };
            if (this.mediaSearchQuery) params.search = this.mediaSearchQuery;
            if (this.mediaFilter) params.mime = this.mediaFilter;
            
            this.fetchData('/media', params).then(data => {
                $('#section-content').html(this.renderMedia(data));
            }).catch(err => {
                $('#section-content').html(`<div class="p-8 text-center text-red-500">Error loading media. Please try again.</div>`);
            });
        },

        selectMediaItem: function(event, id) {
            const $item = $(`[data-id="${id}"]`);
            const $checkbox = $item.find('.media-checkbox');
            
            if (event.target.closest('button') || event.target.type === 'checkbox') return;
            
            $checkbox.prop('checked', !$checkbox.prop('checked'));
            $item.toggleClass('ring-2 ring-primary-500', $checkbox.prop('checked'));
            
            if ($checkbox.prop('checked')) {
                this.selectedMedia.add(id);
            } else {
                this.selectedMedia.delete(id);
            }
            
            this.updateBulkActions();
        },

        updateBulkActions: function() {
            const count = this.selectedMedia.size;
            $('#selected-count').text(count);
            $('#media-bulk-actions').toggleClass('hidden', count === 0);
        },

        clearSelection: function() {
            this.selectedMedia.clear();
            $('.media-checkbox').prop('checked', false);
            $('.media-item').removeClass('ring-2 ring-primary-500');
            this.updateBulkActions();
        },

        bulkDeleteMedia: async function() {
            if (!confirm(`Delete ${this.selectedMedia.size} selected items?`)) return;
            
            try {
                const response = await fetch(`${wpfdVars.restUrl}/media/bulk-delete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpfdVars.restNonce
                    },
                    body: JSON.stringify({ ids: Array.from(this.selectedMedia) })
                });
                
                if (response.ok) {
                    this.clearSelection();
                    this.loadMediaPage(1);
                }
            } catch (err) {
                console.error('Bulk delete error:', err);
                alert('Error deleting items. Please try again.');
            }
        },

        viewMedia: function(id) {
            const item = this.mediaData?.items?.find(i => i.id === id);
            if (!item) return;
            
            const isImage = item.mime?.startsWith('image/');
            const isVideo = item.mime?.startsWith('video/');
            
            let content = '';
            if (isImage) {
                content = `<img src="${item.url}" class="max-w-full max-h-[70vh] object-contain">`;
            } else if (isVideo) {
                content = `<video src="${item.url}" controls class="max-w-full max-h-[70vh]"></video>`;
            } else {
                content = `
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">📄</div>
                        <p class="text-gray-600">${this.escapeHtml(item.title || 'Untitled')}</p>
                        <a href="${item.url}" target="_blank" class="mt-4 inline-block px-4 py-2 bg-primary-600 text-white rounded-lg font-bold hover:bg-primary-700 transition">Download</a>
                    </div>
                `;
            }
            
            this.showModal('View Media', `
                <div class="flex flex-col h-full">
                    <div class="flex-1 flex items-center justify-center bg-gray-900 p-4">${content}</div>
                    <div class="p-4 bg-white border-t">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-bold text-gray-900">${this.escapeHtml(item.title || 'Untitled')}</p>
                                <p class="text-sm text-gray-500">${item.mime || 'Unknown type'}</p>
                            </div>
                            <a href="${item.url}" target="_blank" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-bold hover:bg-gray-200 transition">Open in New Tab</a>
                        </div>
                    </div>
                </div>
            `, true);
        },

        editMedia: function(id) {
            const item = this.mediaData?.items?.find(i => i.id === id);
            if (!item) return;
            
            this.showModal('Edit Media', `
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Title</label>
                        <input type="text" id="edit-media-title" value="${this.escapeHtml(item.title || '')}" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div class="flex gap-3 pt-4">
                        <button onclick="Dashboard.saveMediaEdit(${id})" class="flex-1 px-4 py-2 bg-primary-600 text-white rounded-xl font-bold hover:bg-primary-700 transition">Save Changes</button>
                        <button onclick="closeModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl font-bold hover:bg-gray-200 transition">Cancel</button>
                    </div>
                </div>
            `);
        },

        saveMediaEdit: async function(id) {
            const title = $('#edit-media-title').val();
            
            try {
                const response = await fetch(`${wpfdVars.restUrl}/media/${id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpfdVars.restNonce
                    },
                    body: JSON.stringify({ title: title })
                });
                
                if (response.ok) {
                    closeModal();
                    this.loadMediaPage(this.mediaData?.page || 1);
                } else {
                    alert('Error saving changes. Please try again.');
                }
            } catch (err) {
                console.error('Save error:', err);
                alert('Error saving changes. Please try again.');
            }
        },

        deleteMedia: async function(id) {
            if (!confirm('Are you sure you want to delete this item?')) return;
            
            try {
                const response = await fetch(`${wpfdVars.restUrl}/media/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-WP-Nonce': wpfdVars.restNonce }
                });
                
                if (response.ok) {
                    this.loadMediaPage(this.mediaData?.page || 1);
                } else {
                    alert('Error deleting item. Please try again.');
                }
            } catch (err) {
                console.error('Delete error:', err);
                alert('Error deleting item. Please try again.');
            }
        },

        quickAction: function(action) {
            try {
                switch(action) {
                    case 'new-post':
                        this.showInlinePostCreator('post');
                        break;
                    case 'upload-media':
                        this.openMediaUpload();
                        break;
                    case 'new-page':
                        this.showInlinePostCreator('page');
                        break;
                    case 'new-user':
                        this.openAdminPage('user-new.php');
                        break;
                    case 'settings':
                        this.openAdminPage('options-general.php');
                        break;
                    default:
                        console.warn('Unknown quick action:', action);
                        return;
                }
                
                // Close mobile menus
                if (window.toggleQuickMenu && !$('#quick-menu').hasClass('hidden')) window.toggleQuickMenu();
                if (window.toggleMobileSidebar && !$('#mobile-sidebar').hasClass('pointer-events-none')) window.toggleMobileSidebar();
            } catch (error) {
                console.error('Quick action error:', error);
                this.showNotification('Error performing action. Please try again.', 'error');
            }
        },

        showInlinePostCreator: function(postType) {
            const title = postType === 'page' ? 'Create New Page' : 'Create New Post';
            const icon = postType === 'page' ? 'file' : 'document';
            
            this.showModal(title, `
                <div class="p-6">
                    <form id="inline-post-form" class="space-y-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                ${postType === 'page' ? 'Page Title' : 'Post Title'} *
                            </label>
                            <input type="text" id="post-title" name="post_title" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                placeholder="Enter ${postType === 'page' ? 'page' : 'post'} title...">
                        </div>
                        
                        ${postType === 'post' ? `
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Content</label>
                            <textarea id="post-content" name="post_content" rows="8"
                                class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                placeholder="Write your post content..."></textarea>
                        </div>
                        ` : ''}
                        
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Status</label>
                            <select id="post-status" name="post_status" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="draft">Draft</option>
                                <option value="publish">Published</option>
                                <option value="private">Private</option>
                            </select>
                        </div>
                        
                        <div class="flex justify-end space-x-3 pt-4 border-t border-gray-100">
                            <button type="button" onclick="closeModal()" 
                                class="px-6 py-2 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 font-medium">
                                Cancel
                            </button>
                            <button type="submit" 
                                class="px-6 py-2 bg-primary-600 text-white rounded-xl hover:bg-primary-700 font-medium">
                                Create ${postType === 'page' ? 'Page' : 'Post'}
                            </button>
                        </div>
                    </form>
                </div>
            `, false, this.getIconSvg(icon));
            
            // Setup form submission
            $('#inline-post-form').on('submit', (e) => {
                e.preventDefault();
                this.createInlinePost(postType);
            });
        },

        createInlinePost: async function(postType) {
            const $form = $('#inline-post-form');
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();
            
            // Show loading state
            $submitBtn.prop('disabled', true).text('Creating...');
            
            const formData = {
                title: $('#post-title').val(),
                content: $('#post-content').val() || '',
                status: $('#post-status').val(),
                type: postType
            };
            
            try {
                const response = await fetch(`${wpfdVars.ajaxUrl}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'wpfd_create_post',
                        post_data: JSON.stringify(formData),
                        nonce: wpfdVars.nonce
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.showNotification(`${postType === 'page' ? 'Page' : 'Post'} created successfully!`, 'success');
                    closeModal();
                    
                    // Refresh the posts/pages list
                    this.loadSection(postType + 's');
                } else {
                    throw new Error(result.data.message || 'Creation failed');
                }
            } catch (error) {
                console.error('Create post error:', error);
                this.showNotification(`Error creating ${postType}: ${error.message}`, 'error');
            } finally {
                $submitBtn.prop('disabled', false).text(originalText);
            }
        },

        showNotification: function(message, type = 'info') {
            const colors = {
                success: 'bg-green-50 border-green-200 text-green-800',
                error: 'bg-red-50 border-red-200 text-red-800',
                info: 'bg-blue-50 border-blue-200 text-blue-800',
                warning: 'bg-yellow-50 border-yellow-200 text-yellow-800'
            };
            
            const notification = $(`
                <div class="fixed top-4 right-4 z-50 ${colors[type]} border rounded-lg p-4 shadow-lg max-w-sm notification-item">
                    <div class="flex items-start">
                        <div class="flex-1">
                            <p class="text-sm font-medium">${message}</p>
                        </div>
                        <button onclick="this.parentElement.parentElement.remove()" class="ml-3 text-${type === 'error' ? 'red' : type === 'success' ? 'green' : 'blue'}-600 hover:opacity-75">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            `);
            
            $('body').append(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        deleteItem: async function(id, type) {
            if (!confirm(`Are you sure you want to delete this ${type}? This action cannot be undone.`)) {
                return;
            }

            try {
                const response = await fetch(`${wpfdVars.ajaxUrl}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'wpfd_delete_item',
                        item_id: id,
                        item_type: type,
                        nonce: wpfdVars.nonce
                    })
                });

                const result = await response.json();

                if (result.success) {
                    this.showNotification(`${type.charAt(0).toUpperCase() + type.slice(1)} deleted successfully`, 'success');
                    // Refresh the current section
                    this.loadSection(type + 's');
                } else {
                    throw new Error(result.data.message || 'Delete failed');
                }
            } catch (error) {
                console.error('Delete error:', error);
                this.showNotification(`Error deleting ${type}: ${error.message}`, 'error');
            }
        },

        getIconSvg: function(iconName) {
            const icons = {
                'edit': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>',
                'file': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path>',
                'document': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2z"></path><polyline points="14 2 14 8 20 8"></polyline>',
                'upload': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>',
                'image': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>',
                'message': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>',
                'user': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>',
                'settings': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>'
            };
            return icons[iconName] || icons['document'];
        },

        showModal: function(title, content, fullWidth = false, icon = null) {
            const $modal = $('#modal-container');
            const $content = $('#modal-content');
            
            // Mobile optimized width/height
            if (fullWidth) {
                $content.removeClass('max-w-lg').addClass('max-w-6xl w-[95%] h-[90vh]');
            } else {
                $content.addClass('max-w-lg').removeClass('max-w-6xl w-[95%] h-[90vh]');
            }
            
            const iconHtml = icon ? `
                <div class="w-10 h-10 bg-primary-100 text-primary-600 rounded-xl flex items-center justify-center shrink-0 mr-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">${icon}</svg>
                </div>
            ` : '';
            
            $content.html(`
                <div class="p-6 border-b border-gray-100 flex justify-between items-center shrink-0 bg-white">
                    <div class="flex items-center">
                        ${iconHtml}
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">${title}</h3>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="window.location.reload()" class="p-2 text-gray-400 hover:text-primary-600 hover:bg-gray-100 rounded-lg transition" title="Refresh">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        </button>
                        <button onclick="closeModal()" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Close">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-hidden bg-gray-50">${content}</div>
            `);

            $modal.removeClass('hidden').addClass('flex');
            $content.addClass('flex flex-col');
            setTimeout(() => $content.removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100'), 10);
        },

        closeModal: function() {
            const $modal = $('#modal-container');
            const $content = $('#modal-content');
            $content.addClass('scale-95 opacity-0').removeClass('scale-100 opacity-100');
            setTimeout(() => $modal.addClass('hidden').removeClass('flex'), 300);
        },
        setupNotifications: function() {
            const $btn = $('#notification-btn');
            
            // Initial fetch
            this.fetchNotifications();
            
            // Toggle modal
            $btn.on('click', (e) => {
                e.stopPropagation();
                this.showNotificationsModal();
            });
            
            // Auto refresh notifications every 2 minutes
            setInterval(() => this.fetchNotifications(), 120000);
        },

        fetchNotifications: async function() {
            try {
                const response = await fetch(`${wpfdVars.restUrl}/dashboard/notifications`, {
                    headers: { 'X-WP-Nonce': wpfdVars.restNonce }
                });
                
                if (!response.ok) throw new Error('Failed to fetch notifications');
                
                const data = await response.json();
                this.updateNotificationCounter(data.unread_count);
                this.notifications = data.notifications;
            } catch (error) {
                console.error('Notification error:', error);
            }
        },

        updateNotificationCounter: function(count) {
            const $counter = $('#notification-counter');
            if (count > 0) {
                $counter.text(count > 9 ? '9+' : count).removeClass('hidden');
            } else {
                $counter.addClass('hidden');
            }
        },

        getOrCreateNotificationsModal: function() {
            let $modal = $('#notifications-modal-container');
            if ($modal.length === 0) {
                $modal = $(`
                    <div id="notifications-modal-container" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
                        <div id="notifications-modal-content" class="bg-white rounded-3xl shadow-2xl w-[90%] max-w-md max-h-[80vh] flex flex-col overflow-hidden scale-95 opacity-0 transition-all duration-200">
                            <!-- Header -->
                            <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                                    <h3 class="text-lg font-bold text-gray-900">Notifications</h3>
                                    <span id="notifications-modal-count" class="px-2 py-0.5 bg-primary-100 text-primary-700 rounded-full text-xs font-bold hidden">0</span>
                                </div>
                                <button class="notification-close-btn p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                            <!-- Content -->
                            <div id="notifications-modal-list" class="flex-1 overflow-y-auto">
                                <div class="p-8 text-center text-gray-400">
                                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                                    <p>No new notifications</p>
                                </div>
                            </div>
                            <!-- Footer -->
                            <div class="p-4 border-t border-gray-100 bg-gray-50 flex items-center justify-between">
                                <button class="mark-all-read-btn text-sm text-primary-600 font-bold hover:text-primary-700 transition">
                                    Mark all as read
                                </button>
                                <a href="/dashboard/activity" class="text-sm text-gray-600 hover:text-gray-900 transition flex items-center gap-1">
                                    View all
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                `);
                $('body').append($modal);
                
                // Event delegation for proper event handling
                $modal.on('click', (e) => {
                    if (e.target === $modal[0]) {
                        this.hideNotifications();
                    }
                });
                
                $modal.find('.notification-close-btn').on('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.hideNotifications();
                });
                
                $modal.find('.mark-all-read-btn').on('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.markAllNotificationsRead();
                });
                
                // Keyboard navigation
                $modal.on('keydown', (e) => {
                    if (e.key === 'Escape') {
                        this.hideNotifications();
                    }
                });
            }
            return $modal;
        },

        showNotificationsModal: function() {
            const $modal = this.getOrCreateNotificationsModal();
            const $content = $('#notifications-modal-content');
            
            this.renderNotificationsInModal();
            
            // Show modal with proper classes
            $modal.removeClass('hidden').addClass('flex items-center justify-center');
            setTimeout(() => {
                $content.removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
            }, 10);
            
            // Clear counter after showing
            setTimeout(() => this.updateNotificationCounter(0), 1000);
        },

        hideNotifications: function() {
            const $modal = $('#notifications-modal-container');
            const $content = $('#notifications-modal-content');
            
            // Fallback: Force hide if elements don't exist
            if (!$modal.length) {
                $('#notification-dropdown').removeClass('show');
                return;
            }
            
            if ($content.length) {
                // First animate out the content
                $content.removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
                
                // Then hide the modal container
                setTimeout(() => {
                    $modal.addClass('hidden').removeClass('flex items-center justify-center');
                    // Reset content classes for next show
                    $content.removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
                }, 200);
            } else {
                // Force hide modal if content doesn't exist
                $modal.addClass('hidden').removeClass('flex items-center justify-center');
            }
            
            // Also hide old dropdown if exists
            $('#notification-dropdown').removeClass('show');
        },

        renderNotificationsInModal: function() {
            const $list = $('#notifications-modal-list');
            const $count = $('#notifications-modal-count');
            
            if (!this.notifications || this.notifications.length === 0) {
                $list.html(`
                    <div class="p-8 text-center text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        <p>No new notifications</p>
                    </div>
                `);
                $count.addClass('hidden');
                return;
            }

            let unreadCount = 0;
            let html = '';
            this.notifications.forEach(note => {
                if (!note.read) unreadCount++;
                const icon = note.type === 'comment' ? '💬' : (note.type === 'post' ? '📝' : '👤');
                html += `
                    <div class="notification-item flex items-start gap-4 p-4 hover:bg-gray-50 cursor-pointer border-b border-gray-50 last:border-0 ${note.read ? '' : 'bg-primary-50/30'}" onclick="window.location.href='${note.url || '#'}'">
                        <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center text-xl shrink-0">${icon}</div>
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-gray-900 ${note.read ? '' : 'font-bold'}">${note.title}</div>
                            <div class="text-sm text-gray-500 mt-0.5">${note.message}</div>
                            <div class="text-xs text-gray-400 mt-1">${this.formatRelativeTime(note.time)}</div>
                        </div>
                        ${!note.read ? `<div class="w-2 h-2 bg-primary-500 rounded-full shrink-0 mt-2"></div>` : ''}
                    </div>
                `;
            });
            $list.html(html);
            
            if (unreadCount > 0) {
                $count.text(unreadCount).removeClass('hidden');
            } else {
                $count.addClass('hidden');
            }
        },

        markAllNotificationsRead: function() {
            if (this.notifications) {
                this.notifications.forEach(note => note.read = true);
                this.renderNotificationsInModal();
                this.updateNotificationCounter(0);
            }
        },

        formatRelativeTime: function(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);
            
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            return Math.floor(diff / 86400) + 'd ago';
        },
    };

    $(document).ready(() => Dashboard.init());

})(jQuery);
