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

        if ($(window).width() <= 768) {
            $('.obn-sidebar').removeClass('open');
            $('.obn-overlay').fadeOut(300);
        }
    });
    // Handle URL hash or localStorage view switching on load
    let initialTarget = '';
    const afterReloadView = localStorage.getItem('obn-after-reload-view');
    if (afterReloadView) {
        initialTarget = afterReloadView.replace(/^obn-view-/, '');
        localStorage.removeItem('obn-after-reload-view');
        localStorage.removeItem('obn_active_view');
    } else if (window.location.hash.startsWith('#view=')) {
        initialTarget = window.location.hash.replace('#view=', '');
    } else {
        const stored = localStorage.getItem('obn_active_view');
        if (stored) initialTarget = stored;
    }

    if (initialTarget) {
        setTimeout(function () {
            obn_switch_view(initialTarget);
        }, 150);
    }
});

// Global View Switcher
window.obn_switch_view = function (target) {
    const $ = jQuery;
    target = (target || '').replace(/^obn-view-/, '');
    if (!target) {
        return;
    }

    console.log('Switching to view:', target);
    window.location.hash = 'view=' + target;
    localStorage.setItem('obn_active_view', target);

    $('.obn-view-section').removeClass('active').hide();
    const $view = $('#obn-view-' + target);
    if ($view.length) {
        $view.removeClass('hidden').stop(true, true).fadeIn(300).addClass('active');
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
