function obnSwitchTab(tabName) {
    // Buttons
    const buttons = document.querySelectorAll('.obn-tab');
    buttons.forEach(btn => btn.classList.remove('active'));

    // Forms
    const forms = document.querySelectorAll('.obn-form-wrapper');
    forms.forEach(form => form.classList.remove('active'));

    // Activate selected
    if (tabName === 'login') {
        buttons[0].classList.add('active');
        document.getElementById('obn-login-form').classList.add('active');
    } else {
        buttons[1].classList.add('active');
        document.getElementById('obn-register-form').classList.add('active');
    }
}

jQuery(document).ready(function ($) {
    if (!$('.obn-dashboard-wrapper').length) {
        return;
    }

    // Define global fallback for Frontend_Accounting_ajax to ensure compatibility
    if (typeof obn_ajax !== 'undefined') {
        window.Frontend_Accounting_ajax = window.Frontend_Accounting_ajax || obn_ajax;
    }

    // Register Handling
    $('#obn-register').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const btn = form.find('button[type="submit"]');
        const msg = form.find('.obn-message');

        btn.prop('disabled', true).text('Registering...');
        msg.removeClass('success error').hide();

        const data = {
            action: 'obn_accountant_register',
            security: obn_ajax.nonce,
            username: form.find('input[name="username"]').val(),
            email: form.find('input[name="email"]').val(),
            password: form.find('input[name="password"]').val()
        };

        $.post(obn_ajax.ajax_url, data, function (response) {
            btn.prop('disabled', false).text('Register');

            if (response.success) {
                msg.addClass('success').text(response.data.message);
                form[0].reset();
                setTimeout(function () {
                    obnSwitchTab('login');
                }, 2000);
            } else {
                msg.addClass('error').text(response.data.message);
            }
        });
    });

    // Login Handling
    $('#obn-login').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const btn = form.find('button[type="submit"]');
        const msg = form.find('.obn-message');

        btn.prop('disabled', true).text('Logging in...');
        msg.removeClass('success error').hide();

        const data = {
            action: 'obn_accountant_login',
            security: obn_ajax.nonce,
            username: form.find('input[name="username"]').val(),
            password: form.find('input[name="password"]').val()
        };

        $.post(obn_ajax.ajax_url, data, function (response) {
            btn.prop('disabled', false).text('Login');

            if (response.success) {
                msg.addClass('success').text(response.data.message);
                if (response.data.redirect) {
                    window.location.href = response.data.redirect;
                }
            } else {
                msg.addClass('error').text(response.data.message);
            }
        });
    });

    // Sidebar Navigation - Main Links
    $(document).on('click', '.obn-nav-link', function (e) {
        e.preventDefault();

        const hasSub = $(this).data('has-sub');

        if (hasSub) {
            // Close all other submenus first (accordion behavior)
            $('.obn-submenu').not($(this).next('.obn-submenu')).slideUp(200);
            $('.obn-nav-link').not($(this)).find('.obn-caret').removeClass('rotate-180');
            
            // Toggle current submenu
            $(this).next('.obn-submenu').slideToggle(200);
            $(this).find('.obn-caret').toggleClass('rotate-180');
        } else {
            // Standard Link
            $('.obn-nav-link, .obn-subnav-link').removeClass('active');
            $(this).addClass('active');

            const target = $(this).data('target');
            if (target) {
                obn_switch_view(target);
            }
        }
    });

    // Sidebar Navigation - Sub Links
    $(document).on('click', '.obn-subnav-link', function (e) {
        e.preventDefault();

        // Active States
        $('.obn-nav-link, .obn-subnav-link').removeClass('active');
        $(this).addClass('active');
        $(this).closest('.obn-submenu').prev('.obn-nav-link').addClass('active');

        // View Switching
        const target = $(this).data('target');
        if (target) {
            obn_switch_view(target);
        }
    });
    // Sidebar Toggle (Mobile)
    $(document).on('click', '.obn-hamburger', function (e) {
        e.preventDefault();
        $('.obn-sidebar').toggleClass('open');
        $('.obn-overlay').fadeToggle(300);
    });

    // Close Sidebar on Overlay Click
    $(document).on('click', '.obn-overlay', function () {
        $('.obn-sidebar').removeClass('open');
        $(this).fadeOut(300);
    });

    // Close Sidebar when a menu item is clicked (Mobile)
    $(document).on('click', '.obn-nav-link, .obn-subnav-link', function () {
        // If it's a nav link with a submenu, don't close the sidebar
        if ($(this).data('has-sub')) {
            return;
        }

        if ($(window).width() <= 1024) {
            $('.obn-sidebar').removeClass('open');
            $('.obn-overlay').fadeOut(300);
        }
    });
    // Handle URL hash, query string, or localStorage view switching on load
    let initialTarget = '';
    const afterReloadView = localStorage.getItem('obn-after-reload-view');
    if (afterReloadView) {
        initialTarget = afterReloadView.replace(/^obn-view-/, '');
        localStorage.removeItem('obn-after-reload-view');
        localStorage.removeItem('obn_active_view');
    } else if (window.location.hash.startsWith('#view=')) {
        initialTarget = window.location.hash.replace('#view=', '');
    } else {
        const urlParams = new URLSearchParams(window.location.search);
        const queryView = urlParams.get('view');
        if (queryView) {
            initialTarget = queryView;
        } else {
            const stored = localStorage.getItem('obn_active_view');
            if (stored) initialTarget = stored;
        }
    }

    // Default to dashboard when no view is specified
    if (!initialTarget) {
        initialTarget = 'dashboard';
    }

    if (initialTarget) {
        if (initialTarget !== 'dashboard') {
            $('#obn-view-dashboard').removeClass('active').hide();
        }
        obn_switch_view(initialTarget);
    }

    // Intercept clicks on links that have href="?view=..." to switch views without page reload
    $(document).on('click', 'a[href*="?view="], a[href^="?view="]', function(e) {
        const href = $(this).attr('href');
        if (!href) return;
        let targetView = '';
        let hasOtherParams = false;
        
        if (href.startsWith('?')) {
            const urlParams = new URLSearchParams(href);
            targetView = urlParams.get('view');
            for (const key of urlParams.keys()) {
                if (key !== 'view') {
                    hasOtherParams = true;
                    break;
                }
            }
        } else {
            try {
                const url = new URL(href, window.location.origin);
                targetView = url.searchParams.get('view');
                for (const key of url.searchParams.keys()) {
                    if (key !== 'view') {
                        hasOtherParams = true;
                        break;
                    }
                }
            } catch (err) {
                // Not a valid URL, skip
            }
        }

        // Only intercept if we have a target view AND no other query parameters (like action, id) are present
        if (targetView && !hasOtherParams) {
            const cleanTarget = targetView.replace(/^obn-view-/, '');
            const $view = $('#obn-view-' + cleanTarget);
            if ($view.length) {
                e.preventDefault();
                window.obn_switch_view(cleanTarget);
            }
        }
    });
    // Column toggle button handler
    $(document).on('click', '.obn-column-toggle-btn', function(e) {
        e.stopPropagation();
        $(this).next('.obn-column-dropdown').toggleClass('hidden');
    });
    // Click outside to close dropdown
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.obn-column-dropdown, .obn-column-toggle-btn').length) {
            $('.obn-column-dropdown').addClass('hidden');
        }
    });
    // Column hide/show based on checkbox
    $(document).on('change', '.obn-col-hide', function() {
        var colIdx = $(this).data('column');
        var $table = $($(this).data('table'));
        if ($(this).is(':checked')) {
            $table.find('tr > td:nth-child(' + (colIdx + 1) + '), th:nth-child(' + (colIdx + 1) + ')').show();
        } else {
            $table.find('tr > td:nth-child(' + (colIdx + 1) + '), th:nth-child(' + (colIdx + 1) + ')').hide();
        }
    });
});

// Global View Switcher
window.obn_switch_view = function (target) {
    const $ = jQuery;
    target = (target || '').replace(/^obn-view-/, '');
    if (!target) {
        return;
    }

    console.log('Switching to view:', target);
    const urlParams = new URLSearchParams(window.location.search);
    const queryView = (urlParams.get('view') || '').replace(/^obn-view-/, '');
    if (queryView === target) {
        if (window.location.hash) {
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }
    } else {
        window.location.hash = 'view=' + target;
    }
    localStorage.setItem('obn_active_view', target);

    $('.obn-view-section').removeClass('active').hide();
    const $view = $('#obn-view-' + target);
    if ($view.length) {
        $view.removeClass('hidden').stop(true, true).fadeIn(300, function() {
            // Scroll content area to top after animation completes
            $('.obn-content-area').scrollTop(0);
            // Trigger custom event for view activation
            $(document).trigger('obn_view_activated', [target]);
        }).addClass('active');
        // Update Sidebar Active state
        $('.obn-nav-link, .obn-subnav-link').removeClass('active');
        const $sidebarLink = $(`.obn-nav-link[data-target="${target}"], .obn-subnav-link[data-target="${target}"]`).first();
        if ($sidebarLink.length) {
            $sidebarLink.addClass('active');
            if ($sidebarLink.hasClass('obn-subnav-link')) {
                $sidebarLink.closest('.obn-submenu').show().prev('.obn-nav-link').addClass('active');
            }
        }
    } else {
        console.error('View element not found: #obn-view-' + target);
    }
};
