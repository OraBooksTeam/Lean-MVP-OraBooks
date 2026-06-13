/**
 * WP Frontend Dashboard - UI Enhancements
 * Adds smooth animations, micro-interactions, and enhanced UX
 * Works with Tailwind CSS and gradient UI
 */

(function() {
    'use strict';

    /**
     * Initialize UI enhancements when DOM is ready
     */
    document.addEventListener('DOMContentLoaded', function() {
        initSmoothAnimations();
        initMicroInteractions();
        initGradientAnimations();
        initSmoothScrolling();
        initCardEffects();
        initButtonRipples();
        initProgressBars();
        initTooltips();
        initModalAnimations();
        initLoadingStates();
        initFormEnhancements();
    });

    /**
     * Initialize smooth animations for page elements
     */
    function initSmoothAnimations() {
        // Animate elements on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('wpfd-animate-in');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        // Observe all animatable elements
        document.querySelectorAll('.wpfd-animate-on-scroll').forEach(el => {
            el.classList.add('wpfd-animate-pending');
            observer.observe(el);
        });

        // Add CSS for animations
        const style = document.createElement('style');
        style.textContent = `
            .wpfd-animate-pending {
                opacity: 0;
                transform: translateY(20px);
                transition: opacity 0.6s ease, transform 0.6s ease;
            }
            .wpfd-animate-in {
                opacity: 1;
                transform: translateY(0);
            }
            @keyframes wpfd-fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            @keyframes wpfd-fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes wpfd-slideIn {
                from {
                    opacity: 0;
                    transform: translateX(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Initialize micro-interactions for better UX
     */
    function initMicroInteractions() {
        // Add focus effects to inputs
        document.querySelectorAll('input[type="text"], input[type="email"], input[type="password"], textarea, select').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('wpfd-input-focused');
            });

            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('wpfd-input-focused');
            });
        });
    }

    /**
     * Initialize gradient animations
     */
    function initGradientAnimations() {
        // Animate gradient backgrounds on hover
        const gradientElements = document.querySelectorAll('[class*="gradient"], .button-primary, .page-title-action');
        
        gradientElements.forEach(el => {
            el.addEventListener('mouseenter', function() {
                this.style.backgroundSize = '200% 200%';
                this.style.animation = 'gradientShift 3s ease infinite';
            });

            el.addEventListener('mouseleave', function() {
                this.style.backgroundSize = '100% 100%';
                this.style.animation = 'none';
            });
        });

        // Add gradient animation keyframes
        const style = document.createElement('style');
        style.textContent = `
            @keyframes gradientShift {
                0% { background-position: 0% 50%; }
                50% { background-position: 100% 50%; }
                100% { background-position: 0% 50%; }
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Initialize smooth scrolling
     */
    function initSmoothScrolling() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    e.preventDefault();
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    /**
     * Initialize card effects
     */
    function initCardEffects() {
        // Add hover popup effect to cards
        document.querySelectorAll('.wrap, .postbox').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
                this.style.boxShadow = '0 20px 40px rgba(0, 0, 0, 0.15)';
                this.style.transition = 'transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                this.style.zIndex = '10';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
                this.style.boxShadow = '';
                this.style.zIndex = '';
            });
        });
    }

    /**
     * Initialize button ripple effects
     */
    function initButtonRipples() {
        document.querySelectorAll('.button, .button-primary, .button-secondary').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = e.clientX - rect.left - size / 2 + 'px';
                ripple.style.top = e.clientY - rect.top - size / 2 + 'px';
                ripple.classList.add('wpfd-ripple');
                
                this.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 600);
            });
        });

        // Add ripple CSS
        const style = document.createElement('style');
        style.textContent = `
            .button, .button-primary, .button-secondary {
                position: relative;
                overflow: hidden;
            }
            .wpfd-ripple {
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.4);
                transform: scale(0);
                animation: ripple 0.6s linear;
                pointer-events: none;
            }
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Initialize progress bars with animation
     */
    function initProgressBars() {
        const progressBars = document.querySelectorAll('[data-progress]');
        
        progressBars.forEach(bar => {
            const target = parseInt(bar.getAttribute('data-progress'));
            animateProgressBar(bar, target);
        });

        function animateProgressBar(element, target) {
            let current = 0;
            const increment = target / 50;
            const interval = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(interval);
                }
                element.style.width = current + '%';
            }, 20);
        }
    }

    /**
     * Initialize enhanced tooltips
     */
    function initTooltips() {
        document.querySelectorAll('[data-tooltip]').forEach(el => {
            el.addEventListener('mouseenter', showTooltip);
            el.addEventListener('mouseleave', hideTooltip);
            el.addEventListener('mousemove', moveTooltip);
        });

        function showTooltip(e) {
            const text = this.getAttribute('data-tooltip');
            const tooltip = document.createElement('div');
            tooltip.className = 'wpfd-enhanced-tooltip';
            tooltip.textContent = text;
            document.body.appendChild(tooltip);
            
            this._tooltip = tooltip;
            moveTooltip.call(this, e);
            
            setTimeout(() => tooltip.classList.add('wpfd-tooltip-visible'), 10);
        }

        function hideTooltip() {
            if (this._tooltip) {
                this._tooltip.classList.remove('wpfd-tooltip-visible');
                setTimeout(() => {
                    if (this._tooltip && this._tooltip.parentNode) {
                        this._tooltip.parentNode.removeChild(this._tooltip);
                    }
                    this._tooltip = null;
                }, 200);
            }
        }

        function moveTooltip(e) {
            if (this._tooltip) {
                const tooltip = this._tooltip;
                const rect = this.getBoundingClientRect();
                
                tooltip.style.left = e.clientX + 10 + 'px';
                tooltip.style.top = e.clientY - 30 + 'px';
            }
        }

        // Add tooltip CSS
        const style = document.createElement('style');
        style.textContent = `
            .wpfd-enhanced-tooltip {
                position: fixed;
                background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
                color: white;
                padding: 8px 12px;
                border-radius: 8px;
                font-size: 13px;
                font-weight: 500;
                z-index: 100000;
                pointer-events: none;
                opacity: 0;
                transform: translateY(10px);
                transition: opacity 0.2s ease, transform 0.2s ease;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            }
            .wpfd-tooltip-visible {
                opacity: 1;
                transform: translateY(0);
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Initialize modal animations
     */
    function initModalAnimations() {
        // Animate modals when they open
        const observer = new MutationObserver((mutations) => {
            mutations.forEach(mutation => {
                mutation.addedNodes.forEach(node => {
                    if (node.classList && (node.classList.contains('media-modal') || node.classList.contains('thickbox'))) {
                        node.style.animation = 'wpfd-modalFadeIn 0.3s ease';
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        // Add modal animation CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes wpfd-modalFadeIn {
                from {
                    opacity: 0;
                    transform: scale(0.95);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Initialize enhanced loading states
     */
    function initLoadingStates() {
        // Add skeleton loading for dynamic content
        const style = document.createElement('style');
        style.textContent = `
            .wpfd-skeleton {
                background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
                background-size: 200% 100%;
                animation: wpfd-skeleton-loading 1.5s infinite;
                border-radius: 4px;
            }
            @keyframes wpfd-skeleton-loading {
                0% { background-position: 200% 0; }
                100% { background-position: -200% 0; }
            }
            .wpfd-skeleton-text {
                height: 16px;
                margin-bottom: 8px;
            }
            .wpfd-skeleton-avatar {
                width: 40px;
                height: 40px;
                border-radius: 50%;
            }
            .wpfd-skeleton-button {
                height: 36px;
                width: 100px;
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Initialize form enhancements
     */
    function initFormEnhancements() {
        // Add floating labels
        document.querySelectorAll('.form-table td, .form-wrap label').forEach(label => {
            const input = label.querySelector('input, textarea, select');
            if (input) {
                input.addEventListener('focus', () => {
                    label.classList.add('wpfd-label-active');
                });
                input.addEventListener('blur', () => {
                    if (!input.value) {
                        label.classList.remove('wpfd-label-active');
                    }
                });
            }
        });

        // Add validation feedback
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const inputs = this.querySelectorAll('input[required], textarea[required], select[required]');
                let isValid = true;
                
                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        isValid = false;
                        input.classList.add('wpfd-input-error');
                        
                        input.addEventListener('input', function() {
                            this.classList.remove('wpfd-input-error');
                        }, { once: true });
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    showNotification('Please fill in all required fields.', 'error');
                }
            });
        });

        // Add form enhancement CSS
        const style = document.createElement('style');
        style.textContent = `
            .wpfd-input-error {
                border-color: #ef4444 !important;
                box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
                animation: wpfd-shake 0.5s ease;
            }
            @keyframes wpfd-shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-5px); }
                75% { transform: translateX(5px); }
            }
            .wpfd-label-active {
                color: #3b82f6 !important;
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Show notification
     */
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `wpfd-notification wpfd-notification-${type}`;
        notification.innerHTML = `
            <div class="wpfd-notification-content">
                <span class="wpfd-notification-message">${message}</span>
                <button class="wpfd-notification-close">&times;</button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => notification.classList.add('wpfd-notification-visible'), 10);
        setTimeout(() => {
            notification.classList.remove('wpfd-notification-visible');
            setTimeout(() => notification.remove(), 300);
        }, 5000);

        notification.querySelector('.wpfd-notification-close').addEventListener('click', () => {
            notification.classList.remove('wpfd-notification-visible');
            setTimeout(() => notification.remove(), 300);
        });
    }

    // Expose functions globally
    window.WPFDUI = {
        showNotification,
        animateProgressBar
    };

    console.log('WPFD UI Enhancements initialized');
})();
