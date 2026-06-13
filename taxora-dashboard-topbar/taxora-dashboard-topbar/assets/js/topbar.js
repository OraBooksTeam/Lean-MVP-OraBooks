(function () {
    'use strict';

    const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

    // --- Clock ---
    function updateClock() {
        var now = new Date();
        var el = document.getElementById('taxora-clock');
        if (el) {
            el.textContent = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
        }
        el = document.getElementById('taxora-date');
        if (el) {
            el.textContent = now.toLocaleDateString('en-US', {
                weekday: 'short',
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            });
        }
    }
    updateClock();
    setInterval(updateClock, 1000);

    // --- Calendar ---
    var calDate = new Date();

    function renderCalendar() {
        var year = calDate.getFullYear();
        var month = calDate.getMonth();
        var monthEl = document.getElementById('taxora-calendar-month');
        if (monthEl) {
            monthEl.textContent = new Date(year, month).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        }
        var grid = document.getElementById('taxora-calendar-grid');
        if (!grid) return;
        grid.innerHTML = '';
        var dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        dayHeaders.forEach(function (d) {
            var h = document.createElement('div');
            h.className = 'taxora-calendar-day-header';
            h.textContent = d;
            grid.appendChild(h);
        });
        var firstDay = new Date(year, month, 1).getDay();
        var daysInMonth = new Date(year, month + 1, 0).getDate();
        var today = new Date();
        for (var i = 0; i < firstDay; i++) grid.appendChild(document.createElement('div'));
        for (var day = 1; day <= daysInMonth; day++) {
            var el = document.createElement('div');
            el.className = 'taxora-calendar-day';
            el.textContent = day;
            if (year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
                el.classList.add('today');
            }
            el.addEventListener('click', closeCalendar);
            grid.appendChild(el);
        }
    }

    function changeMonth(dir) {
        calDate.setMonth(calDate.getMonth() + dir);
        renderCalendar();
    }

    // --- Dropdowns ---
    function toggleLanguage() {
        var menu = document.getElementById('taxora-language-menu');
        if (menu) {
            menu.classList.toggle('show');
            closeOtherDropdowns('taxora-language-menu');
        }
    }

    function toggleSettings() {
        var menu = document.getElementById('taxora-settings-menu');
        if (menu) {
            menu.classList.toggle('show');
            closeOtherDropdowns('taxora-settings-menu');
        }
    }

    function toggleCalendar() {
        var cal = document.getElementById('taxora-calendar');
        if (cal) {
            cal.classList.toggle('show');
            closeOtherDropdowns('taxora-calendar');
            if (cal.classList.contains('show')) renderCalendar();
        }
    }

    function closeOtherDropdowns(exceptId) {
        ['taxora-language-menu', 'taxora-settings-menu', 'taxora-calendar'].forEach(function (id) {
            if (id === exceptId) return;
            var el = document.getElementById(id);
            if (el) el.classList.remove('show');
        });
    }

    function closeCalendar() {
        var cal = document.getElementById('taxora-calendar');
        if (cal) cal.classList.remove('show');
    }

    // --- Language ---
    var langLabels = { en: 'English', bn: 'Bangla', ar: 'Arabic' };

    var translations = {
        bn: {
            'My Account': 'আমার অ্যাকাউন্ট',
            'Upgrade Plan': 'প্ল্যান আপগ্রেড করুন',
            'Logout': 'লগ আউট',
            'Overview': 'ওভারভিউ',
            'Posts': 'পোস্ট',
            'Media Library': 'মিডিয়া লাইব্রেরি',
            'Settings': 'সেটিংস',
            'Users': 'ব্যবহারকারী',
            'Profile': 'প্রোফাইল',
            'Content': 'কন্টেন্ট',
            'Assets': 'সম্পদ',
            'Create Post': 'পোস্ট তৈরি করুন',
            'Create Page': 'পেজ তৈরি করুন',
            'Recent Posts': 'সাম্প্রতিক পোস্ট',
            'Statistics': 'পরিসংখ্যান',
            'Total Posts': 'মোট পোস্ট',
            'Total Pages': 'মোট পেজ',
            'Members': 'সদস্য',
            'Activity Overview': 'কার্যকলাপ ওভারভিউ',
            'Available Features': 'উপলব্ধ ফিচার',
            'Home': 'হোম'
        },
        ar: {
            'My Account': 'حسابي',
            'Upgrade Plan': 'ترقية الخطة',
            'Logout': 'تسجيل الخروج',
            'Overview': 'نظرة عامة',
            'Posts': 'المشاركات',
            'Media Library': 'مكتبة الوسائط',
            'Settings': 'الإعدادات',
            'Users': 'المستخدمون',
            'Profile': 'الملف الشخصي',
            'Content': 'المحتوى',
            'Assets': 'الأصول',
            'Create Post': 'إنشاء مشاركة',
            'Create Page': 'إنشاء صفحة',
            'Recent Posts': 'المشاركات الأخيرة',
            'Statistics': 'الإحصائيات',
            'Total Posts': 'إجمالي المشاركات',
            'Total Pages': 'إجمالي الصفحات',
            'Members': 'الأعضاء',
            'Activity Overview': 'نظرة عامة على النشاط',
            'Available Features': 'الميزات المتاحة',
            'Home': 'الرئيسية'
        }
    };

    function getSavableTexts() {
        return document.querySelectorAll(
            '#taxora-settings-menu a, .nav-btn span, .widget-title, .widget h1, .widget p, ' +
            '.text-sm.font-medium.text-gray-500, h3.text-lg.font-bold.text-gray-900, ' +
            '.mobile-nav-btn span, button[onclick*="quickAction"] span, #page-title, ' +
            '.taxora-topbar-left a'
        );
    }

    function storeOriginals() {
        getSavableTexts().forEach(function (el) {
            if (!el.dataset.oraOrig) el.dataset.oraOrig = el.textContent;
        });
    }

    function switchLanguage(lang) {
        var selected = langLabels[lang] ? lang : 'en';
        localStorage.setItem('taxora_language', selected);
        storeOriginals();

        // Close menu
        var menu = document.getElementById('taxora-language-menu');
        if (menu) menu.classList.remove('show');

        // Update language button
        var langBtn = document.querySelector('.taxora-dropdown-btn');
        if (langBtn) langBtn.textContent = (langLabels[selected] || 'English') + ' ▼';

        // Translate all savable elements
        var els = getSavableTexts();
        els.forEach(function (el) {
            var orig = el.dataset.oraOrig || el.textContent;
            if (selected === 'en') {
                el.textContent = orig;
            } else {
                var t = translations[selected];
                el.textContent = t && t[orig] ? t[orig] : orig;
            }
        });

        // Update menu items active state
        var items = {
            en: document.querySelector('a[onclick*="switchLanguage(\'en\')"]'),
            bn: document.querySelector('a[onclick*="switchLanguage(\'bn\')"]'),
            ar: document.querySelector('a[onclick*="switchLanguage(\'ar\')"]')
        };
        Object.keys(items).forEach(function (k) {
            if (items[k]) {
                items[k].classList.toggle('active', k === selected);
                items[k].classList.toggle('selected', k === selected);
            }
        });

        // RTL for Arabic
        document.documentElement.dir = selected === 'ar' ? 'rtl' : 'ltr';
        document.documentElement.lang = selected;

        // Persist via AJAX
        var vars = window.taxoraTopbarVars || {};
        var ajaxUrl = vars.ajaxUrl || '/wp-admin/admin-ajax.php';
        var nonce = vars.langNonce || '';
        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('action=taxora_update_user_language&lang=' + encodeURIComponent(selected) + '&nonce=' + encodeURIComponent(nonce));
    }

    // --- Navigation ---
    function taxoraDashboardBack() {
        if (window.history.length > 1) window.history.back();
    }

    function taxoraDashboardForward() {
        if (window.history.length > 1) window.history.forward();
    }

    // --- Upgrade Plan ---
    function upgradePlan() {
        var btn = document.querySelector('button[data-section="upgrade"]');
        if (btn) btn.click();
        closeOtherDropdowns('taxora-settings-menu');
    }

    // --- Init ---
    function initTopbar() {
        // Retry until topbar exists in DOM
        if (!document.getElementById('taxora-topbar')) {
            setTimeout(initTopbar, 100);
            return;
        }

        // Apply saved language
        var saved = localStorage.getItem('taxora_language');
        if (saved && langLabels[saved]) switchLanguage(saved);

        // Click outside closes dropdowns
        document.addEventListener('click', function (e) {
            var dropdowns = document.querySelectorAll('.taxora-dropdown-menu, .taxora-calendar-dropdown');
            dropdowns.forEach(function (d) {
                if (!d.contains(e.target) &&
                    !e.target.closest('.taxora-dropdown-btn') &&
                    !e.target.closest('.taxora-calendar-btn')) {
                    d.classList.remove('show');
                }
            });
        });
    }

    // Handle case where topbar is injected after DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTopbar);
    } else {
        initTopbar();
    }

    // Also watch for dynamic injection via MutationObserver
    var observer = new MutationObserver(function () {
        if (document.getElementById('taxora-topbar')) {
            observer.disconnect();
            initTopbar();
        }
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });

    // Expose functions globally for onclick handlers
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
