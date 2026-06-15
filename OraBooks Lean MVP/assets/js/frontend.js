/**
 * OraBooks Frontend JavaScript
 */
jQuery(document).ready(function($) {
    
    // Toggle partner fields based on user type
    $('#reg-user-type').on('change', function() {
        var isPartner = $(this).val() === 'partner';
        $('.orabooks-partner-only').toggle(isPartner);
        $('.orabooks-customer-only').toggle(!isPartner);
        if (isPartner) {
            $('.orabooks-partner-org').toggle(
                $('#reg-partner-type').val() === 'agency' || 
                $('#reg-partner-type').val() === 'reseller' || 
                $('#reg-partner-type').val() === 'strategic_partner'
            );
        }
    });
    
    // Toggle organization name for partner types
    $('#reg-partner-type').on('change', function() {
        var val = $(this).val();
        $('.orabooks-partner-org').toggle(
            val === 'agency' || val === 'reseller' || val === 'strategic_partner'
        );
    });
    
    // Registration form
    $('#orabooks-register-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $msg = $('#orabooks-register-message');
        
        // Check password match
        if ($('#reg-password').val() !== $('#reg-confirm-password').val()) {
            $msg.removeClass('success').addClass('error').text('Passwords do not match').show();
            return;
        }
        
        $msg.hide();
        $form.find('button').prop('disabled', true).text('Creating...');
        
        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_register',
            email: $('#reg-email').val(),
            password: $('#reg-password').val(),
            user_type: $('#reg-user-type').val(),
            partner_type: $('#reg-partner-type').val(),
            organization_name: $('#reg-org-name').val(),
            partner_code: $('#reg-partner-code').val(),
            accept_terms: $('#reg-accept-terms').is(':checked') ? 1 : 0
        }, function(response) {
            if (response.error) {
                $msg.removeClass('success').addClass('error').text(response.message).show();
                $form.find('button').prop('disabled', false).text('Create Account');
            } else {
                $msg.removeClass('error').addClass('success').text('Registration successful! Verification email sent. Check your inbox.').show();
                $form.find('button').prop('disabled', false).text('Create Account');
            }
        }).fail(function() {
            $msg.removeClass('success').addClass('error').text('An error occurred. Please try again.').show();
            $form.find('button').prop('disabled', false).text('Create Account');
        });
    });
    
    // Login form
    $('#orabooks-login-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $msg = $('#orabooks-login-message');
        
        $msg.hide();
        $form.find('button').prop('disabled', true).text('Logging in...');
        
        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_login',
            email: $('#login-email').val(),
            password: $('#login-password').val()
        }, function(response) {
            if (response.error) {
                $msg.removeClass('success').addClass('error').text(response.message).show();
                $form.find('button').prop('disabled', false).text('Log In');
            } else {
                if (response.data.requires_2fa) {
                    // Show 2FA challenge
                    $msg.removeClass('error').addClass('success').text('Please enter your 2FA code.').show();
                    // Redirect or show 2FA input
                } else if (response.data.needs_tier_selection) {
                    window.location.href = '/tier-selection/';
                } else if (response.data.redirect_to) {
                    window.location.href = response.data.redirect_to;
                } else {
                    $msg.removeClass('error').addClass('success').text('Login successful! Redirecting...').show();
                    // Store token in localStorage
                    if (response.data.token) {
                        localStorage.setItem('orabooks_token', response.data.token);
                    }
                    setTimeout(function() {
                        window.location.href = '/dashboard/';
                    }, 1000);
                }
            }
        }).fail(function() {
            $msg.removeClass('success').addClass('error').text('An error occurred. Please try again.').show();
            $form.find('button').prop('disabled', false).text('Log In');
        });
    });
    
    // Check subdomain availability
    $('#orabooks-check-subdomain').on('click', function() {
        var subdomain = $('#tier-subdomain').val().toLowerCase().trim();
        var $status = $('#orabooks-subdomain-status');
        
        if (!subdomain) return;
        
        $status.text('Checking...').css('color', '#888');
        
        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_check_subdomain',
            subdomain: subdomain
        }, function(response) {
            if (response.data.available) {
                $status.text('✓ Available').css('color', '#2e7d32');
            } else {
                $status.text('✗ ' + response.data.message).css('color', '#c41a1a');
            }
        });
    });
    
    // Tier selection
    $('#orabooks-tier-form').on('submit', function(e) {
        e.preventDefault();
        var $msg = $('#orabooks-tier-message');
        
        $msg.hide();
        
        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_select_tier',
            tier: $('input[name="tier"]:checked').val(),
            subdomain: $('#tier-subdomain').val()
        }, function(response) {
            if (response.error) {
                $msg.removeClass('success').addClass('error').text(response.message).show();
            } else {
                $msg.removeClass('error').addClass('success').text('Organization created! Redirecting...').show();
                if (response.data.token) {
                    localStorage.setItem('orabooks_token', response.data.token);
                }
                if (response.data.redirect_to) {
                    setTimeout(function() {
                        window.location.href = response.data.redirect_to;
                    }, 1500);
                }
            }
        });
    });
    
    // Copy partner code
    $('#orabooks-copy-code').on('click', function() {
        var codeInput = document.getElementById('orabooks-partner-code');
        codeInput.select();
        document.execCommand('copy');
        $(this).text('Copied!');
        setTimeout($.proxy(function() { $(this).text('Copy Code'); }, this), 2000);
    });
    
    // Load partner info on onboarding page
    if ($('#orabooks-partner-info').length) {
        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_get_partner_info'
        }, function(response) {
            if (!response.error && response.data) {
                $('#orabooks-partner-code').val(response.data.partner_code);
                $('#orabooks-partner-details').html(
                    '<p><strong>Type:</strong> ' + response.data.partner_type + '</p>' +
                    (response.data.organization_name ? '<p><strong>Organization:</strong> ' + response.data.organization_name + '</p>' : '') +
                    '<p><strong>Active Customers:</strong> ' + response.data.active_customers + '</p>' +
                    '<p><strong>Total Attributions:</strong> ' + response.data.total_attributions + '</p>'
                );
                
                var $status = $('#orabooks-partner-status');
                $status.addClass(response.data.status);
                var statusText = {
                    'pending_review': '⏳ Awaiting admin approval. Your code is not yet active.',
                    'active': '✅ Your code is active. Share it to earn commissions.',
                    'disabled': '🚫 Your code has been disabled. Contact support.',
                    'inactive': '🚫 Your partner code is inactive. Contact support to reactivate.'
                }[response.data.status] || '';
                $status.text(statusText);
            }
        });
    }
});