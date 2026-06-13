/**
 * TaxOra Theme Toggle
 * Premium Dark/Light Mode Switcher with localStorage persistence
 */

(function() {
    'use strict';

    // Prevent duplicate initialization
    if (window.TaxOraTheme) {
        return;
    }

    // Theme storage key
    const THEME_STORAGE_KEY = 'taxora_theme_mode';
    const THEME_CLASS_PREFIX = 'taxora-';

    // Theme modes
    const THEMES = {
        LIGHT: 'light',
        DARK: 'dark'
    };

    /**
     * Get saved theme from localStorage
     * @returns {string} Theme mode ('light' or 'dark')
     */
    function getSavedTheme() {
        try {
            const saved = localStorage.getItem(THEME_STORAGE_KEY);
            if (saved && (saved === THEMES.LIGHT || saved === THEMES.DARK)) {
                return saved;
            }
        } catch (e) {
            console.warn('TaxOra: Unable to access localStorage', e);
        }
        return THEMES.LIGHT; // Default to light mode
    }

    /**
     * Save theme to localStorage
     * @param {string} theme - Theme mode ('light' or 'dark')
     */
    function saveTheme(theme) {
        try {
            localStorage.setItem(THEME_STORAGE_KEY, theme);
        } catch (e) {
            console.warn('TaxOra: Unable to save to localStorage', e);
        }
    }

    /**
     * Apply theme to body
     * @param {string} theme - Theme mode ('light' or 'dark')
     */
    function applyTheme(theme) {
        const body = document.body;
        const html = document.documentElement;
        
        // Remove both classes first
        body.classList.remove(THEME_CLASS_PREFIX + THEMES.LIGHT + '-mode');
        body.classList.remove(THEME_CLASS_PREFIX + THEMES.DARK + '-mode');
        
        // Add the appropriate class
        body.classList.add(THEME_CLASS_PREFIX + theme + '-mode');
        
        // Also toggle standard dark/light classes for Tailwind and other components
        if (theme === THEMES.DARK) {
            body.classList.add('dark');
            body.classList.remove('light');
            html.classList.add('dark');
            html.classList.remove('light');
        } else {
            body.classList.add('light');
            body.classList.remove('dark');
            html.classList.add('light');
            html.classList.remove('dark');
        }
        
        // Update toggle button state
        updateToggleState(theme);
    }

    /**
     * Update toggle button visual state
     * @param {string} theme - Current theme mode
     */
    function updateToggleState(theme) {
        const toggle = document.querySelector('.taxora-theme-toggle');
        if (!toggle) return;

        const sunIcon = toggle.querySelector('.sun-icon');
        const moonIcon = toggle.querySelector('.moon-icon');
        
        if (theme === THEMES.DARK) {
            sunIcon.classList.remove('active');
            moonIcon.classList.add('active');
            toggle.setAttribute('aria-label', 'Switch to light mode');
            toggle.setAttribute('aria-pressed', 'true');
        } else {
            sunIcon.classList.add('active');
            moonIcon.classList.remove('active');
            toggle.setAttribute('aria-label', 'Switch to dark mode');
            toggle.setAttribute('aria-pressed', 'false');
        }
    }

    /**
     * Toggle between light and dark mode
     */
    function toggleTheme() {
        console.log('TaxOra Theme: Toggle clicked');
        const currentTheme = getSavedTheme();
        const newTheme = currentTheme === THEMES.LIGHT ? THEMES.DARK : THEMES.LIGHT;
        console.log('TaxOra Theme: Switching from', currentTheme, 'to', newTheme);
        
        const body = document.body;
        const html = document.documentElement;
        
        // Add atomic transition switching class to body and HTML root
        body.classList.add('taxora-theme-switching');
        html.classList.add('taxora-theme-switching');
        
        // Add rotation and opacity transition to icons
        const toggle = document.querySelector('.taxora-theme-toggle');
        if (toggle) {
            const icons = toggle.querySelectorAll('.taxora-theme-toggle-icon');
            icons.forEach(icon => {
                icon.style.opacity = '0.7';
                icon.classList.add('rotating');
                setTimeout(() => {
                    icon.classList.remove('rotating');
                    icon.style.opacity = '';
                }, 280);
            });
        }
        
        // Apply and save new theme in one single step
        applyTheme(newTheme);
        saveTheme(newTheme);
        
        console.log('TaxOra Theme: Theme switched. Body classes:', body.className);
        
        // Dispatch custom event for other components to listen
        const event = new CustomEvent('taxoraThemeChanged', {
            detail: { theme: newTheme }
        });
        document.dispatchEvent(event);
        
        // Remove class automatically after transition is completed
        setTimeout(() => {
            body.classList.remove('taxora-theme-switching');
            html.classList.remove('taxora-theme-switching');
        }, 280);
    }

    /**
     * Initialize theme on page load
     */
    function initTheme() {
        console.log('TaxOra Theme: Initializing...');
        
        // Prevent flash of wrong theme by adding no-transition class temporarily
        document.body.classList.add('no-transition');
        
        // Get saved theme or default to light
        const theme = getSavedTheme();
        console.log('TaxOra Theme: Current theme:', theme);
        
        // Apply theme
        applyTheme(theme);
        console.log('TaxOra Theme: Body classes after apply:', document.body.className);
        
        // Remove no-transition class after a brief delay
        setTimeout(() => {
            document.body.classList.remove('no-transition');
        }, 100);
    }

    /**
     * Setup theme toggle button
     */
    function setupToggleButton() {
        console.log('TaxOra Theme: Setting up toggle button...');
        
        // Find or create toggle button
        let toggle = document.querySelector('.taxora-theme-toggle');
        console.log('TaxOra Theme: Toggle button found:', toggle);
        
        if (!toggle) {
            console.log('TaxOra Theme: Toggle button not found, setting up observer...');
            // If toggle doesn't exist yet, wait for it to be added to DOM
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1) {
                                const foundToggle = node.querySelector ? node.querySelector('.taxora-theme-toggle') : null;
                                if (foundToggle || (node.classList && node.classList.contains('taxora-theme-toggle'))) {
                                    console.log('TaxOra Theme: Toggle button found via observer');
                                    setupToggleButtonHandler(foundToggle || node);
                                }
                            }
                        });
                    }
                });
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
            
            // Also check immediately in case it's already there
            setTimeout(() => {
                toggle = document.querySelector('.taxora-theme-toggle');
                if (toggle) {
                    console.log('TaxOra Theme: Toggle button found after timeout');
                    setupToggleButtonHandler(toggle);
                    observer.disconnect();
                }
            }, 100);
        } else {
            setupToggleButtonHandler(toggle);
        }
    }

    /**
     * Setup event handler for toggle button
     * @param {HTMLElement} toggle - The toggle button element
     */
    function setupToggleButtonHandler(toggle) {
        if (!toggle) {
            console.log('TaxOra Theme: setupToggleButtonHandler called with null toggle');
            return;
        }
        
        console.log('TaxOra Theme: Setting up toggle button handler');
        
        // Remove existing listeners to prevent duplicates
        toggle.removeEventListener('click', toggleTheme);
        
        // Add click handler
        toggle.addEventListener('click', toggleTheme);
        console.log('TaxOra Theme: Click event listener added');
        
        // Add keyboard support
        toggle.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleTheme();
            }
        });
        
        // Initialize button state
        const theme = getSavedTheme();
        console.log('TaxOra Theme: Initializing button state with theme:', theme);
        updateToggleState(theme);
    }

    /**
     * Listen for system theme preference changes
     */
    function setupSystemThemeListener() {
        if (window.matchMedia) {
            const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');
            
            // Only respect system preference if user hasn't manually set a preference
            if (!localStorage.getItem(THEME_STORAGE_KEY)) {
                const systemTheme = darkModeQuery.matches ? THEMES.DARK : THEMES.LIGHT;
                applyTheme(systemTheme);
            }
            
            // Listen for system theme changes
            darkModeQuery.addEventListener('change', function(e) {
                if (!localStorage.getItem(THEME_STORAGE_KEY)) {
                    const systemTheme = e.matches ? THEMES.DARK : THEMES.LIGHT;
                    applyTheme(systemTheme);
                }
            });
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initTheme();
            setupToggleButton();
            setupSystemThemeListener();
        });
    } else {
        // DOM already loaded
        initTheme();
        setupToggleButton();
        setupSystemThemeListener();
    }

    // Expose functions globally for external use
    window.TaxOraTheme = {
        toggle: toggleTheme,
        setTheme: function(theme) {
            if (theme === THEMES.LIGHT || theme === THEMES.DARK) {
                applyTheme(theme);
                saveTheme(theme);
            }
        },
        getTheme: getSavedTheme,
        THEMES: THEMES,
        // Debug function to manually test theme
        debug: function() {
            console.log('=== TaxOra Theme Debug Info ===');
            console.log('Current theme:', getSavedTheme());
            console.log('Body classes:', document.body.className);
            console.log('Toggle button:', document.querySelector('.taxora-theme-toggle'));
            console.log('Sun icon:', document.querySelector('.sun-icon'));
            console.log('Moon icon:', document.querySelector('.moon-icon'));
            console.log('localStorage:', localStorage.getItem(THEME_STORAGE_KEY));
            console.log('================================');
        }
    };

})();
