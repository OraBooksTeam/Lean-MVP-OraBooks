jQuery(document).ready(function ($) {
    if (window && window.console && console.info) console.info('Orabooks frontend script loaded');
    // Add small debug badge to confirm CSS loaded (removed after verification)
    if (!document.querySelector('.orabooks-css-loaded-badge')) {
        var badge = document.createElement('div');
        badge.className = 'orabooks-css-loaded-badge';
        badge.innerText = 'OraBooks styles active';
        document.body.appendChild(badge);
        // Remove badge after 8 seconds so it doesn't persist forever
        setTimeout(function () { try { badge.parentNode.removeChild(badge); } catch (e) { } }, 8000);
    }
    // Workspace setup
    $('#orabooks-setup-workspace').on('click', function (e) {
        e.preventDefault();

        var button = $(this);
        var originalText = button.text();

        button.prop('disabled', true).text('Setting up...');

        $.ajax({
            url: orabooksFrontend.ajax_url,
            type: 'POST',
            data: {
                action: 'orabooks_setup_workspace',
                nonce: orabooksFrontend.nonce
            },
            success: function (response) {
                if (response.success) {
                    var messageText = '';
                    if (typeof response.data === 'string') {
                        messageText = response.data;
                    } else if (response.data && response.data.message) {
                        messageText = response.data.message;
                    } else {
                        messageText = 'Workspace setup completed! Your features are now available.';
                    }

                    // Show success message and reload
                    alert(messageText);
                    setTimeout(function () {
                        location.reload();
                    }, 500);
                } else {
                    var errorMsg = (typeof response.data === 'string') ? response.data : (response.data && response.data.message) ? response.data.message : 'An error occurred';
                    alert('Error: ' + errorMsg);
                    button.prop('disabled', false).text(originalText);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr.responseText);
                var errorMsg = 'Network error. Please try again.';

                // Try to parse error response
                if (xhr.responseText) {
                    try {
                        var errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.data && errorResponse.data.message) {
                            errorMsg = errorResponse.data.message;
                        }
                    } catch (e) {
                        // Use default error message
                    }
                }

                alert(errorMsg);
                button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Feature access checking
    $('.feature-access-btn').on('click', function (e) {
        var featureKey = $(this).data('feature-key');

        $.ajax({
            url: orabooksFrontend.ajax_url,
            type: 'POST',
            data: {
                action: 'orabooks_check_feature_access',
                feature_key: featureKey,
                nonce: orabooksFrontend.nonce
            },
            success: function (response) {
                if (response.success && response.data.has_access) {
                    // Allow access
                    return true;
                } else {
                    e.preventDefault();
                    alert('You do not have access to this feature. Please upgrade your plan.');
                }
            }
        });
    });

    // Membership level selection enhancements
    $('.orabooks-level-card').on('mouseenter', function () {
        $(this).addClass('hover');
    }).on('mouseleave', function () {
        $(this).removeClass('hover');
    });

    // Smooth scrolling for feature links
    $('a[href^="#"]').on('click', function (e) {
        e.preventDefault();
        var target = $(this.getAttribute('href'));
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 100
            }, 1000);
        }
    });

    // Form validation enhancements
    $('.orabooks-register-form, .orabooks-checkout-form').on('submit', function (e) {
        var form = $(this);
        var requiredFields = form.find('input[required], select[required]');
        var valid = true;

        requiredFields.each(function () {
            var field = $(this);
            if (!field.val().trim()) {
                field.addClass('error');
                valid = false;
            } else {
                field.removeClass('error');
            }
        });

        if (!valid) {
            e.preventDefault();
            alert('Please fill in all required fields.');
        }
    });

    // Password strength indicator
    $('#user_pass').on('keyup', function () {
        var password = $(this).val();
        var strength = 0;

        if (password.length >= 6) strength++;
        if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
        if (password.match(/\d/)) strength++;
        if (password.match(/[^a-zA-Z\d]/)) strength++;

        var strengthText = ['Very Weak', 'Weak', 'Medium', 'Strong', 'Very Strong'][strength];
        var strengthColor = ['#dc2626', '#ea580c', '#d97706', '#059669', '#16a34a'][strength];

        $('#password-strength').remove();
        $(this).after('<div id="password-strength" style="font-size: 12px; margin-top: 5px; color: ' + strengthColor + '">Strength: ' + strengthText + '</div>');
    });

    // Account section toggles
    $('.account-section h3').on('click', function () {
        $(this).next('.account-details').slideToggle();
    });

    // Account Deletion Modal
    $('#orabooks-delete-account-trigger').on('click', function () {
        $('#orabooks-delete-modal-overlay').css('display', 'flex').hide().fadeIn(300);
    });

    $('#orabooks-close-delete-modal, #orabooks-delete-modal-overlay').on('click', function (e) {
        if (e.target === this || $(this).attr('id') === 'orabooks-close-delete-modal') {
            $('#orabooks-delete-modal-overlay').fadeOut(300);
        }
    });

    $('#orabooks-confirm-delete').on('click', function () {
        var button = $(this);
        var originalText = button.text();
        var reason = $('#orabooks_delete_reason').val();

        if (!confirm('This is your LAST warning. Are you absolutely sure? All data will be wiped.')) {
            return;
        }

        button.prop('disabled', true).text('Deleting...');

        $.ajax({
            url: orabooksFrontend.ajax_url,
            type: 'POST',
            data: {
                action: 'orabooks_delete_own_account',
                nonce: orabooksFrontend.nonce,
                reason: reason
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data);
                    window.location.href = orabooksFrontend.home_url || '/';
                } else {
                    alert('Error: ' + response.data);
                    button.prop('disabled', false).text(originalText);
                }
            },
            error: function () {
                alert('A network error occurred. Account deletion may have failed or timed out.');
                button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Feature card interactions
    $('.orabooks-feature-card').on('click', function () {
        var featureUrl = $(this).find('.feature-action a').attr('href');
        if (featureUrl && featureUrl !== '#') {
            window.location.href = featureUrl;
        }
    });

    // Mobile menu enhancements for feature dropdown
    $('.menu-item-has-children > a').on('click', function (e) {
        if ($(window).width() <= 768) {
            e.preventDefault();
            $(this).parent().toggleClass('submenu-open');
            $(this).next('.sub-menu').slideToggle();
        }
    });

    // Checkout form submission
    $('.orabooks-checkout-form').on('submit', function (e) {
        e.preventDefault();

        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        var originalBtnText = submitBtn.text();

        submitBtn.prop('disabled', true).text('Processing...');

        // AJAX request to process payment
        $.ajax({
            url: orabooksFrontend.ajax_url,
            type: 'POST',
            data: form.serialize() + '&action=orabooks_process_payment' + '&nonce=' + orabooksFrontend.nonce,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    if (response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        alert('Payment processed, but no redirect URL was provided.');
                        submitBtn.prop('disabled', false).text(originalBtnText);
                    }
                } else {
                    var errorMessage = response.data || 'An unknown error occurred.';
                    if (typeof errorMessage === 'object' && errorMessage.message) {
                        errorMessage = errorMessage.message;
                    }
                    alert('Error: ' + errorMessage);
                    submitBtn.prop('disabled', false).text(originalBtnText);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr.responseText);
                var errorMsg = 'A network error occurred. Please try again.';

                // Try to parse a more specific error from the response
                if (xhr.responseText) {
                    try {
                        var errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.data) {
                            errorMsg = errorResponse.data;
                            if (typeof errorMsg === 'object' && errorMsg.message) {
                                errorMsg = errorMsg.message;
                            }
                        }
                    } catch (e) {
                        // Keep the generic network error message
                    }
                }

                alert('Error: ' + errorMsg);
                submitBtn.prop('disabled', false).text(originalBtnText);
            }
        });
    });

    // Dynamic pricing display
    function formatPrice(amount, symbol, position) {
        if (position === 'after') {
            return amount + symbol;
        } else {
            return symbol + amount;
        }
    }

    // Initialize any tooltips
    $('[data-tooltip]').on('mouseenter', function () {
        var tooltipText = $(this).data('tooltip');
        $('body').append('<div class="orabooks-tooltip">' + tooltipText + '</div>');
        $('.orabooks-tooltip').fadeIn(200);
    }).on('mouseleave', function () {
        $('.orabooks-tooltip').fadeOut(200, function () {
            $(this).remove();
        });
    }).on('mousemove', function (e) {
        $('.orabooks-tooltip').css({
            left: e.pageX + 10,
            top: e.pageY + 10
        });
    });
});

// Tooltip styles (injected via JS)
var tooltipStyles = `
.orabooks-tooltip {
    position: absolute;
    background: #1f2937;
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12px;
    z-index: 10000;
    max-width: 200px;
    display: none;
}
.orabooks-tooltip:after {
    content: '';
    position: absolute;
    left: -5px;
    top: 50%;
    transform: translateY(-50%);
    border-right: 5px solid #1f2937;
    border-top: 5px solid transparent;
    border-bottom: 5px solid transparent;
}
`;

// Inject tooltip styles
if (!document.getElementById('orabooks-tooltip-styles')) {
    var styleSheet = document.createElement('style');
    styleSheet.id = 'orabooks-tooltip-styles';
    styleSheet.innerText = tooltipStyles;
    document.head.appendChild(styleSheet);
}