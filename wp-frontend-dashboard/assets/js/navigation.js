/**
 * Navigation & UI Logic for PWA Dashboard
 */

window.WPFD = {
    currentSection: 'overview',
    
    init: function() {
        this.initMobileSidebar();
        this.initQuickMenu();
        this.initDesktopNav();
    },

    toggleMobileSidebar: function() {
        const sidebar = document.getElementById('mobile-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const content = document.getElementById('sidebar-content');
        
        if (sidebar.classList.contains('pointer-events-none')) {
            // Open
            sidebar.classList.remove('pointer-events-none');
            overlay.classList.add('opacity-100');
            content.classList.remove('-translate-x-full');
            // Clone desktop nav if empty
            const mobileNav = document.getElementById('mobile-nav-container');
            if (mobileNav.children.length === 0) {
                const desktopNav = document.querySelector('aside nav').cloneNode(true);
                mobileNav.appendChild(desktopNav);
            }
        } else {
            // Close
            overlay.classList.remove('opacity-100');
            content.classList.add('-translate-x-full');
            setTimeout(() => sidebar.classList.add('pointer-events-none'), 300);
        }
    },

    toggleQuickMenu: function() {
        const menu = document.getElementById('quick-menu');
        const overlay = document.getElementById('quick-menu-overlay');
        const content = document.getElementById('quick-menu-content');
        
        if (menu.classList.contains('hidden')) {
            menu.classList.remove('hidden');
            setTimeout(() => {
                overlay.classList.add('opacity-100');
                content.classList.remove('translate-y-full');
            }, 10);
        } else {
            overlay.classList.remove('opacity-100');
            content.classList.add('translate-y-full');
            setTimeout(() => menu.classList.add('hidden'), 300);
        }
    },

    initMobileSidebar: function() {
        window.toggleMobileSidebar = () => this.toggleMobileSidebar();
        const overlay = document.getElementById('sidebar-overlay');
        if (overlay) overlay.addEventListener('click', () => this.toggleMobileSidebar());
    },

    initQuickMenu: function() {
        window.toggleQuickMenu = () => this.toggleQuickMenu();
        const overlay = document.getElementById('quick-menu-overlay');
        if (overlay) overlay.addEventListener('click', () => this.toggleQuickMenu());
    },

    initDesktopNav: function() {
        // Desktop nav is handled by loadSection
    }
};

document.addEventListener('DOMContentLoaded', () => WPFD.init());
