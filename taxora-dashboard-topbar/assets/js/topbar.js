/**
 * TaxOra Dashboard Topbar - Global Admin & Frontend
 * Namespace: window.TaxOraTopbar
 * Version: 2.2.0
 */
(function () {
	'use strict';

	/* ------------------------------------------------------------------ */
	/*  Namespace                                                         */
	/* ------------------------------------------------------------------ */
	if (window.TaxOraTopbar) {
		return;
	}

	var T = (window.TaxOraTopbar = {});
	var V = window.taxoraTopbarVars || {};

	try {
		var browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
	} catch (e) {}

	/* ------------------------------------------------------------------ */
	/*  Language Persistence Helpers                                      */
	/* ------------------------------------------------------------------ */
	function saveLanguagePref(lang) {
		try { localStorage.setItem('taxora_language', lang); } catch (e) {}
		try { document.cookie = 'taxora_language=' + encodeURIComponent(lang) + '; path=/; max-age=' + (365 * 24 * 60 * 60); } catch (e) {}
	}

	function getLanguagePref() {
		var lang = null;
		try { lang = localStorage.getItem('taxora_language'); } catch (e) {}
		if (lang && lang.match(/^(en|bn|ar)$/)) return lang;
		try {
			var match = document.cookie.match(/(?:^|;\s*)taxora_language=([^;]*)/);
			if (match) lang = decodeURIComponent(match[1]);
		} catch (e) {}
		if (lang && lang.match(/^(en|bn|ar)$/)) return lang;
		return null;
	}

	/* ------------------------------------------------------------------ */
	/*  Clock                                                             */
	/* ------------------------------------------------------------------ */
	function updateClock() {
		var now = new Date();
		var el = document.getElementById('taxora-clock');
		if (el) {
			el.textContent = now.toLocaleTimeString('en-US', {
				hour: '2-digit',
				minute: '2-digit',
				second: '2-digit',
				hour12: true,
			});
		}
		el = document.getElementById('taxora-date');
		if (el) {
			el.textContent = now.toLocaleDateString('en-US', {
				weekday: 'short',
				month: 'long',
				day: 'numeric',
				year: 'numeric',
			});
		}
	}
	updateClock();
	setInterval(updateClock, 1000);

	/* ------------------------------------------------------------------ */
	/*  Calendar                                                          */
	/* ------------------------------------------------------------------ */
	var calDate = new Date();

	function renderCalendar() {
		var year = calDate.getFullYear();
		var month = calDate.getMonth();

		var monthEl = document.getElementById('taxora-calendar-month');
		if (monthEl) {
			monthEl.textContent = new Date(year, month).toLocaleDateString('en-US', {
				month: 'long',
				year: 'numeric',
			});
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

		for (var i = 0; i < firstDay; i++) {
			grid.appendChild(document.createElement('div'));
		}
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

	T.changeMonth = function (dir) {
		calDate.setMonth(calDate.getMonth() + dir);
		renderCalendar();
	};

	/* ------------------------------------------------------------------ */
	/*  Dropdowns                                                         */
	/* ------------------------------------------------------------------ */
	function closeOtherDropdowns(exceptId) {
		['taxora-language-menu', 'taxora-settings-menu', 'taxora-calendar'].forEach(function (id) {
			if (id === exceptId) return;
			var el = document.getElementById(id);
			if (el) el.classList.remove('show');
		});
	}

	T.toggleLanguage = function () {
		var menu = document.getElementById('taxora-language-menu');
		if (menu) {
			menu.classList.toggle('show');
			closeOtherDropdowns('taxora-language-menu');
		}
	};

	T.toggleSettings = function () {
		var menu = document.getElementById('taxora-settings-menu');
		if (menu) {
			menu.classList.toggle('show');
			closeOtherDropdowns('taxora-settings-menu');
		}
	};

	T.toggleCalendar = function () {
		var cal = document.getElementById('taxora-calendar');
		if (cal) {
			cal.classList.toggle('show');
			closeOtherDropdowns('taxora-calendar');
			if (cal.classList.contains('show')) {
				renderCalendar();
			}
		}
	};

	function closeCalendar() {
		var cal = document.getElementById('taxora-calendar');
		if (cal) cal.classList.remove('show');
	}
	T.closeCalendar = closeCalendar;

	/* ------------------------------------------------------------------ */
	/*  Language Switching                                                */
	/* ------------------------------------------------------------------ */
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
			'Home': 'হোম',
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
			'Home': 'الرئيسية',
		},
	};

	function getSavableTexts() {
		return document.querySelectorAll(
			'#taxora-settings-menu a, .nav-btn span, .widget-title, .widget h1, .widget p, ' +
			'.text-sm.font-medium.text-gray-500, h3.text-lg.font-bold.text-gray-900, ' +
			'.mobile-nav-btn span, button[onclick*="quickAction"] span, #page-title'
		);
	}

	function storeOriginals() {
		getSavableTexts().forEach(function (el) {
			if (!el.dataset.oraOrig) el.dataset.oraOrig = el.textContent;
		});
	}

	T.switchLanguage = function (lang) {
		try {
			var selected = langLabels[lang] ? lang : 'en';

			// Persist to both localStorage AND cookie (works across all routes)
			saveLanguagePref(selected);
			storeOriginals();

			// Close dropdown
			var menu = document.getElementById('taxora-language-menu');
			if (menu) menu.classList.remove('show');

			// Update language dropdown button text (shared topbar element)
			var allDropdownBtns = document.querySelectorAll('.taxora-dropdown-btn');
			if (allDropdownBtns.length > 0) {
				allDropdownBtns[0].textContent = (langLabels[selected] || 'English') + ' ▾';
			}

			// Translate settings menu + any dashboard-specific elements
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

			// Highlight active language in dropdown
			['en', 'bn', 'ar'].forEach(function (k) {
				var a = document.querySelector(
					'a[onclick*="switchLanguage(\'' + k + '\')"], a[onclick*="TaxOraTopbar.switchLanguage(\'' + k + '\')"]'
				);
				if (a) {
					a.classList.toggle('active', k === selected);
					a.classList.toggle('selected', k === selected);
				}
			});

			// Set document-level language attributes
			document.documentElement.dir = selected === 'ar' ? 'rtl' : 'ltr';
			document.documentElement.lang = selected;

			// AJAX persist to user meta (works globally for all routes)
			var ajaxUrl = V.ajaxUrl || '/wp-admin/admin-ajax.php';
			var nonce = V.langNonce || '';
			var xhr = new XMLHttpRequest();
			xhr.open('POST', ajaxUrl, true);
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			xhr.send('action=taxora_update_user_language&lang=' + encodeURIComponent(selected) + '&nonce=' + encodeURIComponent(nonce));
		} catch (e) {
			if (typeof console !== 'undefined') console.warn('TaxOra language switch error:', e);
		}
	};

	/* ------------------------------------------------------------------ */
	/*  Navigation / History                                              */
	/* ------------------------------------------------------------------ */
	T.back = function () {
		if (window.history.length > 1) window.history.back();
	};

	T.forward = function () {
		if (window.history.length > 1) window.history.forward();
	};

	/* ------------------------------------------------------------------ */
	/*  Upgrade Plan                                                      */
	/* ------------------------------------------------------------------ */
	T.upgradePlan = function () {
		var btn = document.querySelector('button[data-section="upgrade"]');
		if (btn) btn.click();
		closeOtherDropdowns('taxora-settings-menu');
	};

	/* ------------------------------------------------------------------ */
	/*  Body Offset / Content Spacing                                     */
	/* ------------------------------------------------------------------ */
	function applyBodyOffset(isInit) {
		var root = document.getElementById('taxora-topbar-root');
		var topbar = document.getElementById('taxora-topbar');
		if (!topbar) return;

		var topbarHeight = topbar.offsetHeight || 56;

		document.body.classList.add('taxora-topbar-active');
		if (!V.isAdmin) {
			document.body.classList.add('taxora-topbar-frontend');
		}

		var adminBar = document.getElementById('wpadminbar');
		var adminBarHeight = adminBar ? adminBar.offsetHeight : 0;
		var gap = 25; // The gap we want below the topbar
		
		var totalOffset = adminBarHeight + topbarHeight + gap;

		document.documentElement.style.setProperty('--taxora-admin-offset', adminBarHeight + 'px');
		document.documentElement.style.setProperty('--taxora-topbar-height', topbarHeight + 'px');
		document.documentElement.style.setProperty('--taxora-total-offset', totalOffset + 'px');

		// Dispatch resize event so other scripts can adjust (only on init to avoid loop)
		if (isInit === true) {
			window.dispatchEvent(new Event('resize'));
		}

		if (V.isAdmin) {
			var wpContent = document.getElementById('wpbody-content');
			if (wpContent) {
				wpContent.style.paddingTop = topbarHeight + 'px';
			}
		} else {
			// On frontend, let CSS handle the padding via the
			// `body.taxora-topbar-active.taxora-topbar-frontend` rules.
			// Remove any inline padding-top so stylesheet can control layout.
			document.body.style.removeProperty('padding-top');
		}
	}

	/* ------------------------------------------------------------------ */
	/*  Init                                                              */
	/* ------------------------------------------------------------------ */
	function init() {
		var topbar = document.getElementById('taxora-topbar');
		if (!topbar) {
			setTimeout(init, 100);
			return;
		}

		// Language restoration handled by taxora-language.js
		applyBodyOffset(true);
		window.addEventListener('resize', function() { applyBodyOffset(false); });

		document.addEventListener('click', function (e) {
			var dropdowns = document.querySelectorAll('.taxora-dropdown-menu, .taxora-calendar-dropdown');
			dropdowns.forEach(function (d) {
				if (
					!d.contains(e.target) &&
					!e.target.closest('.taxora-dropdown-btn') &&
					!e.target.closest('.taxora-calendar-btn')
				) {
					d.classList.remove('show');
				}
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	var observer = new MutationObserver(function () {
		if (document.getElementById('taxora-topbar')) {
			observer.disconnect();
			init();
		}
	});
	observer.observe(document.documentElement, { childList: true, subtree: true });

})();
