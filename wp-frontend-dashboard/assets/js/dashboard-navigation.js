/**
 * Dashboard Navigation Module
 * Handles navigation between sections and mobile menu
 */

(function($) {
    'use strict';

    const DashboardNavigation = {
        currentSection: 'overview',

        init: function() {
            this.setupNavigationHandlers();
            this.setupMobileMenu();
            this.setupKeyboardShortcuts();
        },

        setupNavigationHandlers: function() {
            // Handle navigation button clicks
            $(document).on('click', '[data-section]', (e) => {
                e.preventDefault();
                const section = $(e.currentTarget).data('section');
                this.loadSection(section);
            });

            // Handle quick action buttons
            $(document).on('click', '[data-quick-action]', (e) => {
                e.preventDefault();
                const action = $(e.currentTarget).data('quick-action');
                this.quickAction(action);
            });
        },

        setupMobileMenu: function() {
            // Mobile sidebar toggle
            $(document).on('click', '#mobile-menu-toggle', (e) => {
                e.preventDefault();
                this.toggleMobileSidebar();
            });

            // Close mobile menu when clicking overlay
            $(document).on('click', '#sidebar-overlay', (e) => {
                e.preventDefault();
                this.toggleMobileSidebar();
            });
        },

        setupKeyboardShortcuts: function() {
            $(document).on('keydown', (e) => {
                // Alt + number keys for quick navigation
                if (e.altKey && e.key >= '1' && e.key <= '9') {
                    e.preventDefault();
                    const sections = ['overview', 'posts', 'pages', 'media', 'users', 'settings'];
                    const index = parseInt(e.key) - 1;
                    if (sections[index]) {
                        this.loadSection(sections[index]);
                    }
                }
            });
        },

        loadSection: function(section, params = {}) {
            // Update active navigation state
            this.updateActiveNavigation(section);
            
            // Show loading state
            this.showLoadingState();

            // Load section content via AJAX or REST API
            this.loadSectionContent(section, params)
                .then(() => {
                    this.hideLoadingState();
                    this.currentSection = section;
                })
                .catch((error) => {
                    this.hideLoadingState();
                    this.showError('Failed to load section: ' + error.message);
                });
        },

        updateActiveNavigation: function(section) {
            // Update desktop navigation
            $('.nav-btn').removeClass('bg-primary-500 text-white shadow-lg shadow-primary-500/20');
            $(`[data-section="${section}"]`).addClass('bg-primary-500 text-white shadow-lg shadow-primary-500/20');
            
            // Update mobile navigation
            $('.mobile-nav-btn').removeClass('active-nav');
            $(`[data-section="${section}"]`).addClass('active-nav');
            
            // Update page title
            const sectionTitles = {
                overview: 'Overview',
                posts: 'Posts',
                pages: 'Pages',
                media: 'Media Library',
                users: 'Users',
                settings: 'Settings',
                profile: 'My Profile',
                upgrade: 'Upgrade Plan'
            };
            
            $('#page-title').text(sectionTitles[section] || 'Dashboard');
        },

        loadSectionContent: function(section, params) {
            return new Promise((resolve, reject) => {
                // Try to load from REST API first
                if (this.sections[section] && this.sections[section].endpoint) {
                    fetch(`${wpfdVars.restUrl}${this.sections[section].endpoint}`, {
                        headers: { 'X-WP-Nonce': wpfdVars.restNonce }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Failed to load section data');
                        }
                        return response.json();
                    })
                    .then(data => {
                        this.renderSectionContent(section, data);
                        resolve();
                    })
                    .catch(reject);
                } else if (this.sections[section] && this.sections[section].url) {
                    // For external URLs, load in iframe
                    this.loadIframe(this.sections[section].url, this.sections[section].title);
                    resolve();
                } else {
                    // Fallback: try to render default content
                    this.renderDefaultSection(section);
                    resolve();
                }
            });
        },

        renderSectionContent: function(section, data) {
            const $content = $('#section-content');
            
            // Add transition effect
            $content.addClass('section-hidden');
            
            setTimeout(() => {
                // Use the main dashboard's render methods if available
                if (window.Dashboard && window.Dashboard.renderSection) {
                    $content.html(window.Dashboard.renderSection(section, data));
                } else {
                    // Fallback rendering
                    $content.html(this.getDefaultSectionHTML(section, data));
                }
                
                $content.removeClass('section-hidden');
            }, 200);
        },

        renderDefaultSection: function(section) {
            const html = `
                <div class="bg-white rounded-3xl p-8 shadow-premium border border-gray-100">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">${section.charAt(0).toUpperCase() + section.slice(1)}</h2>
                    <div class="text-center text-gray-500 py-12">
                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m-6-6h12"></path>
                        </svg>
                        <p class="text-lg">This section is under construction.</p>
                        <p class="text-sm mt-2">Please check back later for updates.</p>
                    </div>
                </div>
            `;
            
            $('#section-content').html(html);
        },

        quickAction: function(action) {
            const actions = {
                'new-post': () => this.openAdminPage('/wp-admin/post-new.php'),
                'new-page': () => this.openAdminPage('/wp-admin/post-new.php?post_type=page'),
                'upload-media': () => this.openAdminPage('/wp-admin/media-new.php'),
                'new-user': () => this.openAdminPage('/wp-admin/user-new.php')
            };

            if (actions[action]) {
                actions[action]();
            } else {
                this.showError('Unknown action: ' + action);
            }
        },

        openAdminPage: function(url) {
            // Open admin page in new tab or modal
            if (wpfdVars.isAdmin) {
                window.open(url, '_blank');
            } else {
                // For non-admins, try to load in iframe
                this.loadIframe(url, 'Admin Page');
            }
        },

        loadIframe: function(url, title) {
            const $content = $('#section-content');
            const iframe = `
                <div class="bg-white rounded-3xl shadow-premium border border-gray-100 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">${this.escapeHtml(title)}</h3>
                        <button onclick="DashboardNavigation.closeIframe()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <iframe src="${url}" class="w-full h-[600px] border-0" onload="DashboardNavigation.iframeLoaded()"></iframe>
                </div>
            `;
            
            $content.html(iframe);
        },

        closeIframe: function() {
            this.loadSection('overview');
        },

        iframeLoaded: function() {
            // Hide loading indicator when iframe loads
            this.hideLoadingState();
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

        showLoadingState: function() {
            const $content = $('#section-content');
            $content.append(`
                <div id="loading-overlay" class="fixed inset-0 bg-white/80 flex items-center justify-center z-50">
                    <div class="bg-white rounded-2xl p-6 shadow-2xl flex items-center gap-4">
                        <div class="animate-spin w-6 h-6 border-2 border-primary-500 border-t-transparent rounded-full"></div>
                        <span class="text-gray-600">Loading...</span>
                    </div>
                </div>
            `);
        },

        hideLoadingState: function() {
            $('#loading-overlay').remove();
        },

        showError: function(message) {
            const $content = $('#section-content');
            $content.append(`
                <div id="error-message" class="fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-xl shadow-lg z-50 max-w-sm">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>${this.escapeHtml(message)}</span>
                        <button onclick="DashboardNavigation.hideError()" class="ml-4 text-white/80 hover:text-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            `);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                this.hideError();
            }, 5000);
        },

        hideError: function() {
            $('#error-message').fadeOut(300, function() {
                $(this).remove();
            });
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        // Section definitions
        sections: {
            overview: { title: 'Overview', endpoint: '/dashboard/overview' },
            posts: { title: 'Posts', endpoint: '/posts' },
            pages: { title: 'Pages', endpoint: '/pages' },
            users: { title: 'Users', endpoint: '/users' },
            media: { title: 'Media Library', endpoint: '/media' },
            settings: { title: 'Settings', endpoint: '/site-settings' },
            profile: { title: 'My Profile', endpoint: '/users/me' },
            upgrade: { title: 'Upgrade Plan', url: wpfdVars.pricingUrl }
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        DashboardNavigation.init();
    });

    // Make available globally
    window.DashboardNavigation = DashboardNavigation;
    window.loadSection = (s, p) => DashboardNavigation.loadSection(s, p);
    window.quickAction = (a) => DashboardNavigation.quickAction(a);

})(jQuery);
