/**
 * Inventory Dashboard Main Script
 * Handles sidebar toggling, submenu expansion, and mobile responsiveness.
 * Completely separated to avoid conflicts with Accounting module.
 */
document.addEventListener('DOMContentLoaded', function () {
    // Select essential elements
    const sidebar = document.getElementById('inventory-sidebar');
    const mobileToggle = document.getElementById('mobile-sidebar-toggle');
    const overlay = document.getElementById('sidebar-overlay');
    const submenuToggles = document.querySelectorAll('.inventory-menu-toggle');

    /**
     * Mobile Sidebar Toggle
     */
    if (mobileToggle && sidebar && overlay) {
        mobileToggle.addEventListener('click', function () {
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');

            // Toggle Icon (Hamburger to X if needed)
            const icon = mobileToggle.querySelector('i');
            if (icon) {
                if (sidebar.classList.contains('mobile-open')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-xmark');
                } else {
                    icon.classList.remove('fa-xmark');
                    icon.classList.add('fa-bars');
                }
            }
        });

        // Close sidebar when clicking overlay
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');

            const icon = mobileToggle.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-xmark');
                icon.classList.add('fa-bars');
            }
        });
    }

    /**
     * Submenu Toggle Logic
     */
    submenuToggles.forEach(toggle => {
        toggle.addEventListener('click', function () {
            const submenu = this.nextElementSibling;
            if (!submenu) return;

            const isOpen = submenu.classList.contains('open');

            // Close other open submenus (optional, for accordion effect)
            /*
            submenuToggles.forEach(otherToggle => {
                if (otherToggle !== toggle) {
                    const otherSub = otherToggle.nextElementSibling;
                    if (otherSub) {
                        otherSub.classList.remove('open');
                        otherSub.style.maxHeight = null;
                        otherToggle.setAttribute('aria-expanded', 'false');
                    }
                }
            });
            */

            if (isOpen) {
                submenu.classList.remove('open');
                submenu.style.maxHeight = null;
                this.setAttribute('aria-expanded', 'false');
            } else {
                submenu.classList.add('open');
                submenu.style.maxHeight = submenu.scrollHeight + "px";
                this.setAttribute('aria-expanded', 'true');
            }
        });
    });

    /**
     * Helper to show messages (moved from old script.js)
     */
    window.inventoryShowMessage = (elementId, message, isError = false) => {
        const msgDiv = document.getElementById(elementId);
        if (!msgDiv) return;
        msgDiv.textContent = message;
        msgDiv.classList.remove('hidden', 'text-green-600', 'text-red-600', 'bg-green-100', 'bg-red-100');
        msgDiv.classList.add(isError ? 'text-red-600' : 'text-green-600');
        msgDiv.classList.add(isError ? 'bg-red-100' : 'bg-green-100');
        msgDiv.classList.add('rounded-lg', 'p-3', 'mb-4');
        msgDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    // Close mobile sidebar on window resize if screen becomes large
    window.addEventListener('resize', function () {
        if (window.innerWidth > 1024 && sidebar && sidebar.classList.contains('mobile-open')) {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        }
    });

});
