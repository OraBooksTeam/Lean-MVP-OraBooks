/**
 * OraBooks Frontend JavaScript
 */
jQuery(document).ready(function($) {

    // SL-013 OIDC: Remove inline onclick from Google button to prevent race condition.
    // The inline onclick starts navigation to /orabooks-google-login before the AJAX
    // call in our jQuery handler can complete. We override it here at the start of ready().
    $('.orabooks-btn-google').prop('onclick', null).off('click');

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
                    // Show 2FA challenge form (replace login form)
                    orabooksShow2faChallenge(response.data.temp_token, response.data.user_id);
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

    // =============================================
    // GOOGLE OIDC AUTH (SL-013)
    // =============================================

    /**
     * Google OAuth button click — AJAX-initiated flow.
     * Gets auth URL from server, stores state for CSRF, redirects to Google.
     */
    $(document).on('click', '.orabooks-btn-google', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var origText = $btn.text();

        $btn.prop('disabled', true).text('⏳ Connecting to Google...');

        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_oidc_initiate'
        }, function(response) {
            if (response.error) {
                $('#orabooks-login-message')
                    .removeClass('success').addClass('error')
                    .text(response.message).show();
                $btn.prop('disabled', false).text(origText);
            } else {
                // Extract state from auth URL for client-side verification
                var authUrl = response.data.auth_url;
                var queryStart = authUrl.indexOf('?');
                if (queryStart !== -1) {
                    var urlParams = new URLSearchParams(authUrl.substring(queryStart));
                    if (urlParams.get('state')) {
                        sessionStorage.setItem('orabooks_oidc_state', urlParams.get('state'));
                    }
                }
                // Redirect to Google's consent page
                window.location.href = authUrl;
            }
        }).fail(function() {
            $('#orabooks-login-message')
                .removeClass('success').addClass('error')
                .text('Network error. Please try again.').show();
            $btn.prop('disabled', false).text(origText);
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
    
    // =============================================
    // OIDC CALLBACK HANDLER — picks up code+state from URL params
    // after Google redirects back to the login page
    // =============================================
    (function() {
        var urlParams = new URLSearchParams(window.location.search);
        var oidcCode = urlParams.get('code');
        var oidcState = urlParams.get('state');

        if (oidcCode && oidcState) {
            var $msg = $('#orabooks-login-message');
            if ($msg.length) {
                $msg.removeClass('error').addClass('success')
                    .text('⏳ Completing Google authentication...').show();
            }

            // Verify state matches what we stored client-side
            var storedState = sessionStorage.getItem('orabooks_oidc_state');
            if (storedState && storedState !== oidcState) {
                if ($msg.length) {
                    $msg.removeClass('success').addClass('error')
                        .text('OAuth state mismatch. Please try again.').show();
                }
                sessionStorage.removeItem('orabooks_oidc_state');
                return;
            }
            sessionStorage.removeItem('orabooks_oidc_state');

            $.post(orabooks_ajax.ajax_url, {
                action: 'orabooks_oidc_callback',
                code: oidcCode,
                state: oidcState
            }, function(response) {
                if (response.error) {
                    if ($msg.length) {
                        $msg.removeClass('success').addClass('error')
                            .text(response.message).show();
                    }
                } else if (response.data.requires_2fa) {
                    orabooksShow2faChallenge(response.data.temp_token, response.data.user_id);
                } else {
                    if (response.data.token) {
                        localStorage.setItem('orabooks_token', response.data.token);
                    }
                    var redirectTo = response.data.redirect_to || '/dashboard/';
                    window.location.href = redirectTo;
                }
            }).fail(function() {
                if ($msg.length) {
                    $msg.removeClass('success').addClass('error')
                        .text('Network error during Google authentication.').show();
                }
            });

            // Clean URL params without page reload
            if (window.history.replaceState) {
                var cleanUrl = window.location.protocol + '//' +
                    window.location.host + window.location.pathname;
                window.history.replaceState({}, document.title, cleanUrl);
            }
        }
    })();

    // =============================================
    // URL FRAGMENT TOKEN DETECTOR — picks up #token=... or #error=...
    // from the server-side OIDC redirect (server processes code, redirects with fragment)
    // =============================================
    (function() {
        if (window.location.hash) {
            var hash = window.location.hash.substring(1);
            var hashParams = new URLSearchParams(hash);
            var token = hashParams.get('token');
            var error = hashParams.get('error');

            if (token) {
                localStorage.setItem('orabooks_token', token);
                // Clean URL
                if (window.history.replaceState) {
                    window.history.replaceState({}, document.title,
                        window.location.pathname + window.location.search);
                }
            }
            if (error) {
                var $msg = $('#orabooks-login-message');
                if ($msg.length) {
                    $msg.removeClass('success').addClass('error')
                        .text(decodeURIComponent(error)).show();
                }
                if (window.history.replaceState) {
                    window.history.replaceState({}, document.title,
                        window.location.pathname + window.location.search);
                }
            }
        }
    })();

    // =============================================
    // 2FA CHALLENGE UI (SL-013)
    // =============================================

    /**
     * Show 2FA challenge form, hiding the login form.
     * Accepts temp_token and user_id from the requires_2fa response.
     */
    function orabooksShow2faChallenge(tempToken, userId) {
        var $container = $('.orabooks-form-container');

        // Hide login form
        $container.find('#orabooks-login-form').hide();

        // Create 2FA form if it doesn't exist
        if (!$('#orabooks-2fa-form').length) {
            $container.append(
                '<form id="orabooks-2fa-form" class="orabooks-form" style="margin-top:16px;">' +
                    '<h3>Two-Factor Authentication</h3>' +
                    '<p>Enter the 6-digit code from your authenticator app, or use a backup code.</p>' +
                    '<div class="orabooks-form-group">' +
                        '<label for="2fa-otp">Authentication Code</label>' +
                        '<input type="text" id="2fa-otp" name="otp_code" inputmode="numeric" ' +
                               'pattern="[0-9]*" maxlength="6" placeholder="000000" ' +
                               'autocomplete="one-time-code" required>' +
                    '</div>' +
                    '<div class="orabooks-form-group">' +
                        '<label>' +
                            '<input type="checkbox" id="2fa-use-backup"> ' +
                            'Use a backup code instead' +
                        '</label>' +
                    '</div>' +
                    '<div class="orabooks-form-group" id="orabooks-2fa-backup-group" style="display:none;">' +
                        '<label for="2fa-backup">Backup Code</label>' +
                        '<input type="text" id="2fa-backup" name="backup_code" ' +
                               'placeholder="Enter a backup code from when you set up 2FA">' +
                    '</div>' +
                    '<div class="orabooks-form-actions">' +
                        '<button type="submit" class="orabooks-btn orabooks-btn-primary">Verify Code</button>' +
                    '</div>' +
                '</form>' +
                '<div id="orabooks-2fa-message" class="orabooks-message"></div>'
            );
        }

        $('#orabooks-2fa-form').show().data('temp-token', tempToken).data('user-id', userId);
        $('#orabooks-2fa-message').hide();
        $('#2fa-otp').val('').focus();
    }

    // Toggle backup code vs OTP input
    $(document).on('change', '#2fa-use-backup', function() {
        var checked = $(this).is(':checked');
        $('#orabooks-2fa-backup-group').toggle(checked);
        $('#2fa-otp').prop('required', !checked);
        $('#2fa-backup').prop('required', checked);
    });

    // Submit 2FA challenge
    $(document).on('submit', '#orabooks-2fa-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $msg = $('#orabooks-2fa-message');
        var tempToken = $form.data('temp-token');

        $msg.hide();
        $form.find('button').prop('disabled', true).text('Verifying...');

        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_2fa_challenge',
            temp_token: tempToken,
            otp_code: $('#2fa-otp').val(),
            backup_code: $('#2fa-backup').val()
        }, function(response) {
            if (response.error) {
                $msg.removeClass('success').addClass('error').text(response.message).show();
                $form.find('button').prop('disabled', false).text('Verify Code');
                $('#2fa-otp').val('').focus();
            } else {
                $msg.removeClass('error').addClass('success').text('✅ Verified! Redirecting...').show();
                if (response.data.token) {
                    localStorage.setItem('orabooks_token', response.data.token);
                }
                setTimeout(function() {
                    window.location.href = '/dashboard/';
                }, 1000);
            }
        }).fail(function() {
            $msg.removeClass('success').addClass('error').text('Network error. Please try again.').show();
            $form.find('button').prop('disabled', false).text('Verify Code');
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
    
    // =============================================
    // PARTNER ONBOARDING EXPORT (SL-114) — frontend
    // =============================================
    $(document).on('click', '.orabooks-onboarding-export-trigger', function() {
        var $btn = $(this);
        var origText = $btn.text();
        var exportType = $btn.data('export-type') || 'partner_onboarding';
        var format = $btn.data('format') || 'csv';

        $btn.prop('disabled', true).text('⏳ Requesting...');

        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_export_request',
            export_type: exportType,
            format: format,
            parameters: JSON.stringify({
                columns: ['partner_code', 'partner_type', 'status', 'organization_name', 'active_customers', 'total_attributions', 'user_email', 'created_at']
            })
        }, function(response) {
            if (response.error) {
                alert('Error: ' + response.message);
                $btn.prop('disabled', false).text(origText);
            } else {
                $btn.text('✅ Requested').prop('disabled', true);
                var $msg = $('#orabooks-onboarding-export-msg');
                $msg.removeClass('error').addClass('success')
                    .html('✅ Export requested! <a href="?page=orabooks-exports">View status</a>')
                    .css('display', 'block');
                setTimeout(function() {
                    $msg.fadeOut();
                    $btn.text(origText).prop('disabled', false);
                }, 6000);
            }
        }).fail(function() {
            $btn.prop('disabled', false).text(origText);
        });
    });

    // =============================================
    // COMMISSION CONFIG EXPORT (SL-114) — frontend
    // =============================================
    $(document).on('click', '.orabooks-commconfig-export-trigger', function() {
        var $btn = $(this);
        var origText = $btn.text();
        var exportType = $btn.data('export-type') || 'commission_config';
        var format = $btn.data('format') || 'csv';

        $btn.prop('disabled', true).text('⏳ Requesting...');

        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_export_request',
            export_type: exportType,
            format: format,
            parameters: JSON.stringify({
                columns: ['base_monthly_amount', 'max_years', 'yearly_percentages', 'min_payout_threshold', 'customer_active_window_days', 'expiry_accounting_action', 'payout_fee_type', 'payout_fee_rate']
            })
        }, function(response) {
            if (response.error) {
                alert('Error: ' + response.message);
                $btn.prop('disabled', false).text(origText);
            } else {
                $btn.text('✅ Requested').prop('disabled', true);
                var $msg = $('#orabooks-commconfig-export-msg');
                $msg.removeClass('error').addClass('success')
                    .html('✅ Export requested! <a href="?page=orabooks-exports">View status</a>')
                    .css('display', 'block');
                setTimeout(function() {
                    $msg.fadeOut();
                    $btn.text(origText).prop('disabled', false);
                }, 6000);
            }
        }).fail(function() {
            $btn.prop('disabled', false).text(origText);
        });
    });

    // =============================================
    // COMMISSION DASHBOARD
    // =============================================
    
    // Load commission stats
    function orabooksLoadCommissionStats(partnerUserId) {
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_commission_stats',
            partner_user_id: partnerUserId
        }, function(response) {
            if (!response.error && response.data) {
                var d = response.data;
                $('#orabooks-total-earned').text('$' + parseFloat(d.total_earned).toFixed(2));
                $('#orabooks-pending-payout').text('$' + parseFloat(d.pending_payout).toFixed(2));
                $('#orabooks-total-paid').text('$' + parseFloat(d.total_paid).toFixed(2));
                $('#orabooks-total-expired').text('$' + parseFloat(d.total_expired).toFixed(2));
                $('#orabooks-escrow-remaining').text('$' + parseFloat(d.escrow_remaining).toFixed(2));
            }
        });
    }
    
    // Load earned commissions
    function orabooksLoadEarnedCommissions(partnerUserId) {
        var $tbody = $('#orabooks-earned-table-body');
        $tbody.html('<tr><td colspan="6">Loading...</td></tr>');
        
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_commission_earned',
            partner_user_id: partnerUserId
        }, function(response) {
            if (!response.error && response.data) {
                var html = '';
                $.each(response.data, function(i, row) {
                    var statusClass = row.status;
                    var statusLabel = row.status.charAt(0).toUpperCase() + row.status.slice(1);
                    html += '<tr>' +
                        '<td>' + row.customer_email_masked + '</td>' +
                        '<td>' + row.release_month + '</td>' +
                        '<td>$' + parseFloat(row.amount).toFixed(2) + '</td>' +
                        '<td><span class="orabooks-badge orabooks-badge-' + statusClass + '">' + statusLabel + '</span></td>' +
                        '<td>' + row.expires_at + '</td>' +
                        '<td>' + row.earned_at + '</td>' +
                    '</tr>';
                });
                $tbody.html(html || '<tr><td colspan="6">No commissions found</td></tr>');
            }
        });
    }
    
    // Load payout history
    function orabooksLoadPayouts(partnerUserId) {
        var $tbody = $('#orabooks-payouts-table-body');
        $tbody.html('<tr><td colspan="5">Loading...</td></tr>');
        
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_commission_payouts',
            partner_user_id: partnerUserId
        }, function(response) {
            if (!response.error && response.data) {
                var html = '';
                $.each(response.data, function(i, row) {
                    var statusClass = row.status;
                    var statusLabel = row.status.charAt(0).toUpperCase() + row.status.slice(1);
                    html += '<tr>' +
                        '<td>' + (row.payout_date || row.created_at) + '</td>' +
                        '<td>$' + parseFloat(row.gross_amount).toFixed(2) + '</td>' +
                        '<td>$' + parseFloat(row.fee_amount).toFixed(2) + '</td>' +
                        '<td><strong>$' + parseFloat(row.net_amount).toFixed(2) + '</strong></td>' +
                        '<td><span class="orabooks-badge orabooks-badge-' + statusClass + '">' + statusLabel + '</span></td>' +
                    '</tr>';
                });
                $tbody.html(html || '<tr><td colspan="5">No payouts found</td></tr>');
            }
        });
    }
    
    // Load aging report
    function orabooksLoadAging(partnerUserId) {
        var $tbody = $('#orabooks-aging-table-body');
        $tbody.html('<tr><td colspan="2">Loading...</td></tr>');
        
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_commission_aging',
            partner_user_id: partnerUserId
        }, function(response) {
            if (!response.error && response.data) {
                var d = response.data;
                var html = '';
                var buckets = [
                    { label: '0-30 Days', value: d.bucket_0_30 },
                    { label: '31-60 Days', value: d.bucket_31_60 },
                    { label: '61-90 Days', value: d.bucket_61_90 },
                    { label: '90+ Days', value: d.bucket_90_plus },
                    { label: 'Expired', value: d.expired_total }
                ];
                $.each(buckets, function(i, b) {
                    html += '<tr>' +
                        '<td>' + b.label + '</td>' +
                        '<td>$' + parseFloat(b.value || 0).toFixed(2) + '</td>' +
                    '</tr>';
                });
                $tbody.html(html);
            }
        });
    }
    
    // Load escrow schedule
    function orabooksLoadEscrow(partnerUserId) {
        var $tbody = $('#orabooks-escrow-table-body');
        $tbody.html('<tr><td colspan="6">Loading...</td></tr>');
        
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_commission_escrow_schedule',
            partner_user_id: partnerUserId
        }, function(response) {
            if (!response.error && response.data) {
                var html = '';
                $.each(response.data, function(i, row) {
                    var statusClass = row.remaining_amount_status === 'expired' ? 'expired' : 'pending';
                    html += '<tr>' +
                        '<td>' + row.customer_email_masked + '</td>' +
                        '<td>$' + parseFloat(row.total_amount).toFixed(2) + '</td>' +
                        '<td>$' + parseFloat(row.released_amount).toFixed(2) + '</td>' +
                        '<td>$' + parseFloat(row.remaining_amount).toFixed(2) + '</td>' +
                        '<td><div class="orabooks-progress-bar"><div class="orabooks-progress-fill" style="width:' + row.progress_pct + '%"></div><span>' + row.progress_pct + '%</span></div></td>' +
                        '<td><span class="orabooks-badge orabooks-badge-' + statusClass + '">' + row.remaining_amount_status + '</span></td>' +
                    '</tr>';
                });
                $tbody.html(html || '<tr><td colspan="6">No escrow schedules found</td></tr>');
            }
        });
    }
    
    // Tab switching
    $(document).on('click', '.orabooks-tab', function() {
        var tab = $(this).data('tab');
        $('.orabooks-tab').removeClass('orabooks-tab-active');
        $(this).addClass('orabooks-tab-active');
        $('.orabooks-tab-content').removeClass('orabooks-tab-content-active');
        $('#orabooks-tab-' + tab).addClass('orabooks-tab-content-active');
    });
    
    // Initialize commission dashboard
    if ($('.orabooks-commission-dashboard').length) {
        var partnerUserId = orabooks_ajax.current_user_id || 0;
        orabooksLoadCommissionStats(partnerUserId);
        orabooksLoadEarnedCommissions(partnerUserId);
        orabooksLoadPayouts(partnerUserId);
        orabooksLoadAging(partnerUserId);
        orabooksLoadEscrow(partnerUserId);
    }
    
    // =============================================
    // PARTNER DASHBOARD (SL-139)
    // =============================================
    
    // Load full partner dashboard
    function orabooksLoadPartnerDashboard() {
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_partner_dashboard'
        }, function(response) {
            if (response.error) {
                $('#orabooks-partner-dashboard-message').removeClass('success').addClass('error').text(response.message).show();
                return;
            }
            
            var d = response.data;
            
            // Partner code & type
            $('#orabooks-dash-partner-code').val(d.partner_code);
            var typeHtml = '<p><strong>Type:</strong> ' + d.partner_type + '</p>';
            if (d.organization_name) {
                typeHtml += '<p><strong>Organization:</strong> ' + d.organization_name + '</p>';
            }
            typeHtml += '<p><strong>Active Customers:</strong> ' + d.active_customer_count + '</p>';
            $('#orabooks-partner-type-display').html(typeHtml);
            
            // Status Banners
            var banners = '';
            if (d.payout_disabled) {
                banners += '<div class="orabooks-banner orabooks-banner-warning">' +
                    '⚠️ Payout hold: commissions are being tracked but withdrawal is temporarily disabled. ' +
                    '<span class="orabooks-banner-tooltip">⏸️ Contact support for resolution.</span></div>';
            }
            if (d.read_only) {
                banners += '<div class="orabooks-banner orabooks-banner-warning">' +
                    '🔒 Partner program is readonly. Contact support for reactivation.</div>';
            }
            if (d.code_status === 'inactive') {
                banners += '<div class="orabooks-banner orabooks-banner-danger">' +
                    '⚠️ Your partner program is inactive. You have no active customers and no new referral for 12 months. ' +
                    'You cannot earn commissions until reactivated. ' +
                    '<button id="orabooks-show-reactivation" class="orabooks-btn orabooks-btn-secondary orabooks-btn-sm">Request Reactivation</button></div>';
            }
            if (d.is_dormant) {
                banners += '<div class="orabooks-banner orabooks-banner-info">' +
                    '💡 You have no active customers. Refer new customers to earn commissions!</div>';
            }
            $('#orabooks-status-banners').html(banners);
            
            // Attribution stats
            if (d.attribution_stats) {
                $('#orabooks-attr-total').text(d.attribution_stats.total || 0);
                $('#orabooks-attr-verified').text(d.attribution_stats.verified || 0);
                $('#orabooks-attr-pending').text(d.attribution_stats.pending || 0);
            }
            
            // Commission summary
            if (d.commission_summary) {
                $('#orabooks-comm-earned').text('$' + parseFloat(d.commission_summary.total_earned || 0).toFixed(2));
                $('#orabooks-comm-pending').text('$' + parseFloat(d.commission_summary.pending_payout || 0).toFixed(2));
                $('#orabooks-comm-paid').text('$' + parseFloat(d.commission_summary.paid || 0).toFixed(2));
                $('#orabooks-comm-expired').text('$' + parseFloat(d.commission_summary.expired || 0).toFixed(2));
            }
            
            // Attribution table
            var $attrBody = $('#orabooks-attr-table-body');
            if (d.attributions && d.attributions.length) {
                var html = '';
                $.each(d.attributions, function(i, a) {
                    var attrBadge = a.attribution_status === 'verified' ? 
                        '<span class="orabooks-badge orabooks-badge-paid">Verified</span>' : 
                        '<span class="orabooks-badge orabooks-badge-pending">Pending</span>';
                    var commBadge = '';
                    if (a.commission_status === 'paid') commBadge = '<span class="orabooks-badge orabooks-badge-paid">Paid</span>';
                    else if (a.commission_status === 'earned') commBadge = '<span class="orabooks-badge orabooks-badge-earned">Earned</span>';
                    else if (a.commission_status === 'expired') commBadge = '<span class="orabooks-badge orabooks-badge-expired">Expired</span>';
                    else if (a.commission_status === 'qualified') commBadge = '<span class="orabooks-badge orabooks-badge-initiated">Qualified</span>';
                    else commBadge = '<span class="orabooks-badge" style="background:#f5f5f5;color:#999;">—</span>';
                    
                    html += '<tr>' +
                        '<td>' + a.customer_email_masked + '</td>' +
                        '<td>' + a.attribution_date + '</td>' +
                        '<td>' + attrBadge + '</td>' +
                        '<td>' + commBadge + '</td>' +
                    '</tr>';
                });
                $attrBody.html(html);
            } else {
                $attrBody.html('<tr><td colspan="4">No attributions yet</td></tr>');
            }
            
            // Payout breakdown table (Gross/Fee/Net)
            var $payoutBody = $('#orabooks-payout-breakdown-body');
            if (d.payout_breakdown && d.payout_breakdown.length) {
                var html = '';
                $.each(d.payout_breakdown, function(i, p) {
                    var statusBadge = p.status === 'settled' ? '<span class="orabooks-badge orabooks-badge-paid">Paid</span>' :
                        p.status === 'initiated' ? '<span class="orabooks-badge orabooks-badge-initiated">Pending</span>' :
                        '<span class="orabooks-badge">' + p.status + '</span>';
                    html += '<tr>' +
                        '<td>' + p.period + '</td>' +
                        '<td>$' + parseFloat(p.gross).toFixed(2) + '</td>' +
                        '<td>$' + parseFloat(p.fee).toFixed(2) + '</td>' +
                        '<td><strong>$' + parseFloat(p.net).toFixed(2) + '</strong></td>' +
                        '<td>' + statusBadge + '</td>' +
                    '</tr>';
                });
                $payoutBody.html(html);
            } else {
                $payoutBody.html('<tr><td colspan="5">No payouts yet</td></tr>');
            }
        });
    }
    
    // Copy code with audit tracking
    $(document).on('click', '#orabooks-dash-copy-code', function() {
        var codeInput = document.getElementById('orabooks-dash-partner-code');
        if (codeInput) {
            codeInput.select();
            document.execCommand('copy');
            $(this).text('Copied!');
            setTimeout($.proxy(function() { $(this).text('Copy Code'); }, this), 2000);
            
            // Audit event
            $.post(orabooks_ajax.ajax_url, {
                action: 'orabooks_partner_code_copied',
                source: 'dashboard'
            });
        }
    });
    
    // Show reactivation modal
    $(document).on('click', '#orabooks-show-reactivation', function() {
        $('#orabooks-reactivation-modal').show();
    });
    
    // Close modal
    $(document).on('click', '.orabooks-modal-close', function() {
        $('#orabooks-reactivation-modal').hide();
    });
    
    // Submit reactivation
    $(document).on('click', '#orabooks-submit-reactivation', function() {
        var $msg = $('#orabooks-reactivation-message');
        $msg.hide();
        
        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_request_reactivation',
            org_id: 0,
            reason: $('#orabooks-reactivation-reason').val()
        }, function(response) {
            if (response.error) {
                $msg.removeClass('success').addClass('error').text(response.message).show();
            } else {
                $msg.removeClass('error').addClass('success').text('✅ ' + response.message).show();
                $('#orabooks-show-reactivation').prop('disabled', true).text('Reactivation Requested');
                setTimeout(function() {
                    $('#orabooks-reactivation-modal').hide();
                }, 2000);
            }
        });
    });
    
    // Click outside modal to close
    $(document).on('click', function(e) {
        if ($(e.target).hasClass('orabooks-modal')) {
            $('.orabooks-modal').hide();
        }
    });
    
    // Initialize partner dashboard
    if ($('.orabooks-partner-dashboard').length) {
        orabooksLoadPartnerDashboard();
    }
    
    // =============================================
    // NOTIFICATION CENTER (SL-250)
    // =============================================
    
    // Load notification list
    function orabooksLoadNotifications() {
        var $list = $('#orabooks-nc-list');
        $list.html('<p class="orabooks-loading">Loading notifications...</p>');
        
        var params = {
            action: 'orabooks_notifications_list',
            limit: 50
        };
        
        var priority = $('#orabooks-nc-filter-priority').val();
        var status = $('#orabooks-nc-filter-status').val();
        var eventType = $('#orabooks-nc-filter-event').val();
        
        if (priority) params.priority = priority;
        if (status) params.status = status;
        if (eventType) params.event_type = eventType;
        
        $.get(orabooks_ajax.ajax_url, params, function(response) {
            if (!response.error && response.data) {
                var d = response.data;
                
                // Unread badge
                if (d.unread_count > 0) {
                    $('#orabooks-nc-unread-badge').text('🔔 ' + d.unread_count + ' unread notification' + (d.unread_count !== 1 ? 's' : '')).show();
                } else {
                    $('#orabooks-nc-unread-badge').hide();
                }
                
                // List
                var html = '';
                if (d.notifications && d.notifications.length) {
                    $.each(d.notifications, function(i, n) {
                        var unreadClass = !n.is_read ? 'orabooks-nc-item-unread' : '';
                        var priorityLabel = n.priority.charAt(0).toUpperCase() + n.priority.slice(1);
                        var priorityBadge = '<span class="orabooks-badge orabooks-badge-' + n.priority + '">' + priorityLabel + '</span>';
                        
                        html += '<div class="orabooks-nc-item ' + unreadClass + '" data-id="' + n.id + '">' +
                            '<div class="orabooks-nc-item-header">' +
                                '<div class="orabooks-nc-item-title">' + (n.title || n.event_type) + '</div>' +
                                priorityBadge +
                            '</div>' +
                            '<div class="orabooks-nc-item-message">' + (n.message || '') + '</div>' +
                            '<div class="orabooks-nc-item-meta">' +
                                '<span class="orabooks-nc-item-time">' + n.created_at + '</span>' +
                                '<span class="orabooks-nc-item-channel">📨 ' + (n.delivery_channel || 'inapp') + '</span>' +
                                '<span class="orabooks-nc-item-correlation">#' + n.correlation_id.substring(0, 12) + '</span>' +
                                (n.delivery_proof ? ' <a href="#" class="orabooks-nc-view-proof" data-id="' + n.id + '">View proof</a>' : '') +
                            '</div>' +
                        '</div>';
                    });
                } else {
                    html = '<div class="orabooks-nc-empty">✅ No notifications found</div>';
                }
                $list.html(html);
            }
        });
    }
    
    // Mark notification as read on click
    $(document).on('click', '.orabooks-nc-item', function() {
        var $item = $(this);
        var id = $item.data('id');
        
        if (!$item.hasClass('orabooks-nc-item-unread')) return;
        
        $item.removeClass('orabooks-nc-item-unread');
        
        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_notifications_mark_read',
            notification_id: id
        });
    });
    
    // Mark all as read
    $(document).on('click', '#orabooks-nc-mark-all-read', function() {
        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_notifications_mark_all_read'
        }, function(response) {
            if (!response.error) {
                $('.orabooks-nc-item').removeClass('orabooks-nc-item-unread');
                $('#orabooks-nc-unread-badge').hide();
            }
        });
    });
    
    // Apply filter
    $(document).on('click', '#orabooks-nc-filter-apply', function() {
        orabooksLoadNotifications();
    });
    
    // View delivery proof
    $(document).on('click', '.orabooks-nc-view-proof', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $item = $(this).closest('.orabooks-nc-item');
        var id = $item.data('id');
        
        // Find the notification in the loaded data
        // For MVP, show a placeholder
        $('#orabooks-nc-proof-content').text('Loading delivery proof for notification #' + id + '...\n\nProof data available on server side.');
        $('#orabooks-nc-proof-modal').show();
    });
    
    /**
     * Highlight and scroll to a specific notification by ID.
     * Adds an attention-grabbing highlight that fades out over time.
     */
    function orabooksHighlightNotification(notificationId) {
        var $target = $('.orabooks-nc-item[data-id="' + notificationId + '"]');
        if (!$target.length) {
            return;
        }

        // Scroll the notification into view
        $('html, body').animate({
            scrollTop: $target.offset().top - 80
        }, 400);

        // Add highlight class (removes after animation completes)
        $target.addClass('orabooks-nc-highlight');
        setTimeout(function() {
            $target.removeClass('orabooks-nc-highlight');
        }, 3000);

        // Auto-mark as read if unread
        if ($target.hasClass('orabooks-nc-item-unread')) {
            $target.removeClass('orabooks-nc-item-unread');
            $.post(orabooks_ajax.ajax_url, {
                action: 'orabooks_notifications_mark_read',
                notification_id: notificationId
            });
        }
    }

    // =============================================
    // NOTIFICATION DEEP LINK — auto-load a specific notification from ?notification_id= query param
    // Used when user navigates to /notifications/?notification_id=N from a notification email/link
    // =============================================
    (function() {
        var urlParams = new URLSearchParams(window.location.search);
        var notifId = urlParams.get('notification_id');

        if (notifId && $('.orabooks-notification-center').length) {
            // Override the standard init: load notifications, then highlight the target
            var originalLoad = orabooksLoadNotifications;
            // We can't easily intercept the callback, so we poll for the element
            var checkInterval = setInterval(function() {
                var $target = $('.orabooks-nc-item[data-id="' + notifId + '"]');
                if ($target.length) {
                    clearInterval(checkInterval);
                    orabooksHighlightNotification(parseInt(notifId, 10));
                }
            }, 200);

            // Stop polling after 15 seconds (safety net)
            setTimeout(function() {
                clearInterval(checkInterval);
            }, 15000);

            // Clean the notification_id from the URL without reloading
            if (window.history.replaceState) {
                urlParams.delete('notification_id');
                var newSearch = urlParams.toString();
                var cleanUrl = window.location.protocol + '//' +
                    window.location.host + window.location.pathname +
                    (newSearch ? '?' + newSearch : '');
                window.history.replaceState({}, document.title, cleanUrl);
            }
        }
    })();

    // Initialize notification center
    if ($('.orabooks-notification-center').length) {
        orabooksLoadNotifications();
    }
    
    // =============================================
    // NOTIFICATION PREFERENCES
    // =============================================
    
    // Load preferences
    function orabooksLoadPrefs() {
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_notification_preferences_get'
        }, function(response) {
            if (!response.error && response.data) {
                var p = response.data;
                
                // Channels
                if (p.channels) {
                    $('input[name="channels[]"]').each(function() {
                        $(this).prop('checked', p.channels.indexOf($(this).val()) !== -1);
                    });
                }
                
                // Quiet hours
                if (p.quiet_hours_start) $('#prefs-quiet-start').val(p.quiet_hours_start);
                if (p.quiet_hours_end) $('#prefs-quiet-end').val(p.quiet_hours_end);
                
                // Digest
                if (p.digest) $('#prefs-digest').val(p.digest);
                
                // Language
                if (p.language) $('#prefs-language').val(p.language);
                
                // Escalation
                if (p.escalation_enabled !== undefined) {
                    $('input[name="escalation_enabled"]').prop('checked', !!p.escalation_enabled);
                }
            }
        });
    }
    
    // Save preferences
    $(document).on('submit', '#orabooks-nc-prefs-form', function(e) {
        e.preventDefault();
        var $msg = $('#orabooks-nc-prefs-message');
        $msg.hide();
        
        var formData = $(this).serializeArray();
        formData.push({ name: 'action', value: 'orabooks_notification_preferences_save' });
        
        $.post(orabooks_ajax.ajax_url, formData, function(response) {
            if (response.error) {
                $msg.removeClass('success').addClass('error').text(response.message).show();
            } else {
                $msg.removeClass('error').addClass('success').text('✅ ' + response.message).show();
            }
        });
    });
    
    // Initialize preferences
    if ($('.orabooks-notification-preferences').length) {
        orabooksLoadPrefs();
    }
    
    // =============================================
    // NOTIFICATION ADMIN
    // =============================================
    
    // Load org policy
    function orabooksLoadOrgPolicy() {
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_notification_admin_policy_get',
            org_id: 0
        }, function(response) {
            if (!response.error && response.data && response.data.org_id) {
                var p = response.data;
                $('#policy-monthly-budget').val(p.monthly_budget || '');
                
                if (p.mandatory_event_types) {
                    var mandatory = typeof p.mandatory_event_types === 'string' ? JSON.parse(p.mandatory_event_types) : p.mandatory_event_types;
                    $('input[name="mandatory_event_types[]"]').each(function() {
                        $(this).prop('checked', mandatory.indexOf($(this).val()) !== -1);
                    });
                }
                
                if (p.prohibited_channels) {
                    var prohibited = typeof p.prohibited_channels === 'string' ? JSON.parse(p.prohibited_channels) : p.prohibited_channels;
                    $('input[name="prohibited_channels[]"]').each(function() {
                        $(this).prop('checked', prohibited.indexOf($(this).val()) !== -1);
                    });
                }
                
                $('#policy-retention').val(p.retention_override_days || '');
                $('#policy-max-escalation').val(p.max_escalation_attempts || 3);
                
                if (p.escalation_fallback_chain) {
                    var chain = typeof p.escalation_fallback_chain === 'string' ? JSON.parse(p.escalation_fallback_chain) : p.escalation_fallback_chain;
                    $('input[name="escalation_fallback_chain[]"]').each(function() {
                        $(this).prop('checked', chain.indexOf($(this).val()) !== -1);
                    });
                }
            }
        });
    }
    
    // Save org policy
    $(document).on('submit', '#orabooks-nc-policy-form', function(e) {
        e.preventDefault();
        var $msg = $('#orabooks-nc-policy-message');
        $msg.hide();
        
        var formData = $(this).serializeArray();
        formData.push({ name: 'action', value: 'orabooks_notification_admin_policy_save' });
        formData.push({ name: 'org_id', value: 0 });
        
        $.post(orabooks_ajax.ajax_url, formData, function(response) {
            if (response.error) {
                $msg.removeClass('success').addClass('error').text(response.message).show();
            } else {
                $msg.removeClass('error').addClass('success').text('✅ Policy saved').show();
            }
        });
    });
    
    // Load provider health
    function orabooksLoadProviderHealth() {
        var $tbody = $('#orabooks-nc-provider-health-body');
        $tbody.html('<tr><td colspan="7">Loading...</td></tr>');
        
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_notification_admin_provider_health'
        }, function(response) {
            if (!response.error && response.data) {
                var html = '';
                $.each(response.data, function(i, r) {
                    var scoreClass = r.health_score <= 40 ? 'expired' : r.health_score <= 70 ? 'pending' : 'paid';
                    html += '<tr>' +
                        '<td>' + r.channel + '</td>' +
                        '<td>' + r.provider_name + '</td>' +
                        '<td>' + r.region + '</td>' +
                        '<td>' + parseFloat(r.success_rate).toFixed(2) + '%</td>' +
                        '<td>' + r.avg_latency_ms + 'ms</td>' +
                        '<td><span class="orabooks-badge orabooks-badge-' + scoreClass + '">' + r.health_score + '</span></td>' +
                        '<td>' + (r.last_outage_at || '—') + '</td>' +
                    '</tr>';
                });
                $tbody.html(html || '<tr><td colspan="7">No provider data yet. Health scoring cron will populate after deliveries.</td></tr>');
            }
        });
    }
    
    // Refresh provider health
    $(document).on('click', '#orabooks-nc-refresh-health', function() {
        orabooksLoadProviderHealth();
    });
    
    // Export audit bundle
    $(document).on('submit', '#orabooks-nc-audit-export-form', function(e) {
        e.preventDefault();
        var $msg = $('#orabooks-nc-audit-result');
        $msg.hide();
        
        var startDate = $('#audit-start-date').val();
        var endDate = $('#audit-end-date').val();
        
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_notification_admin_audit_export',
            org_id: 0,
            start_date: startDate,
            end_date: endDate
        }, function(response) {
            if (response.error) {
                $msg.removeClass('success').addClass('error').text(response.message).show();
            } else {
                // Download the bundle as JSON file
                var blob = new Blob([JSON.stringify(response, null, 2)], {type: 'application/json'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'notification-audit-bundle-' + startDate + '-to-' + endDate + '.json';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                
                $msg.removeClass('error').addClass('success').text('✅ Bundle exported. Export event is logged for compliance.').show();
            }
        });
    });
    
    // Initialize admin
    if ($('.orabooks-notification-admin').length) {
        orabooksLoadOrgPolicy();
        orabooksLoadProviderHealth();
    }
    
    // =============================================
    // ASYNC QUEUE DASHBOARD
    // =============================================
    
    function orabooksLoadQueueStats() {
        var $statsBody = $('#orabooks-aq-failures-body');
        
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_async_queue_stats'
        }, function(response) {
            if (!response.error && response.data) {
                var d = response.data;
                
                // Summary counts
                $('#aq-total').text(d.total || 0);
                $('#aq-pending').text(d.pending_count || 0);
                $('#aq-processing').text(d.processing_count || 0);
                $('#aq-completed').text(d.completed_count || 0);
                $('#aq-failed').text(d.failed_count || 0);
                $('#aq-dead').text(d.dead_letter_count || 0);
                
                // Performance
                $('#aq-latency').text(d.avg_latency_seconds ? d.avg_latency_seconds + 's' : '—');
                $('#aq-failure-rate').text(d.failure_rate_24h ? d.failure_rate_24h + '%' : '—');
                
                // Recent failures
                if (d.recent_failures && d.recent_failures.length) {
                    var html = '';
                    $.each(d.recent_failures, function(i, job) {
                        var statusBadge = job.status === 'dead_letter'
                            ? '<span class="orabooks-badge orabooks-badge-expired">Dead Letter</span>'
                            : '<span class="orabooks-badge orabooks-badge-pending">Failed</span>';
                        html += '<tr>' +
                            '<td>#' + job.id + '</td>' +
                            '<td>' + (job.job_type || '—') + '</td>' +
                            '<td>' + (job.retry_count || 0) + '</td>' +
                            '<td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;">' + (job.last_error || '—') + '</td>' +
                            '<td>' + (job.last_attempt_at || job.created_at) + '</td>' +
                            '<td><button class="orabooks-btn orabooks-btn-secondary orabooks-btn-sm orabooks-aq-retry" data-id="' + job.id + '">⟳ Retry</button></td>' +
                        '</tr>';
                    });
                    $statsBody.html(html);
                } else {
                    $statsBody.html('<tr><td colspan="6">✅ No recent failures</td></tr>');
                }
            }
        });
    }
    
    // Retry job
    $(document).on('click', '.orabooks-aq-retry', function() {
        var $btn = $(this);
        var jobId = $btn.data('id');
        $btn.prop('disabled', true).text('Retrying...');
        
        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_async_queue_replay',
            job_id: jobId
        }, function(response) {
            if (response.error) {
                alert('Error: ' + response.message);
                $btn.prop('disabled', false).text('⟳ Retry');
            } else {
                $btn.text('✅ Retried').removeClass('orabooks-btn-secondary').addClass('orabooks-badge-paid');
                orabooksLoadQueueStats();
            }
        });
    });
    
    // Refresh stats
    $(document).on('click', '#orabooks-aq-refresh', function() {
        orabooksLoadQueueStats();
    });
    
    // Initialize queue dashboard
    if ($('.orabooks-async-queue-dashboard').length) {
        orabooksLoadQueueStats();
    }
    
    // =============================================
    // EXPORTS (SL-114)
    // =============================================

    // Load exports list
    function orabooksLoadExports(page) {
        page = page || 1;
        var $tbody = $('#orabooks-export-table-body');
        $tbody.html('<tr><td colspan="7">Loading exports...</td></tr>');

        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_exports_list',
            page: page
        }, function(response) {
            if (!response.error && response.data) {
                var d = response.data;

                // Update summary stats
                var pending = 0, ready = 0;
                $('#orabooks-export-total').text(d.total || 0);
                $.each(d.exports, function(i, exp) {
                    if (exp.status === 'pending' || exp.status === 'generating') pending++;
                    if (exp.status === 'ready') ready++;
                });
                $('#orabooks-export-pending').text(pending);
                $('#orabooks-export-ready').text(ready);

                // Build table
                var html = '';
                if (d.exports && d.exports.length) {
                    $.each(d.exports, function(i, exp) {
                        var statusBadge = '';
                        var statusLabel = exp.status.charAt(0).toUpperCase() + exp.status.slice(1);
                        switch (exp.status) {
                            case 'pending': statusBadge = '<span class="orabooks-badge orabooks-badge-pending">⏳ Pending</span>'; break;
                            case 'generating': statusBadge = '<span class="orabooks-badge orabooks-badge-processing">⚙️ Generating</span>'; break;
                            case 'ready': statusBadge = '<span class="orabooks-badge orabooks-badge-paid">✅ Ready</span>'; break;
                            case 'failed': statusBadge = '<span class="orabooks-badge orabooks-badge-failed">❌ Failed</span>'; break;
                            case 'expired': statusBadge = '<span class="orabooks-badge orabooks-badge-expired">⌛ Expired</span>'; break;
                            case 'cancelled': statusBadge = '<span class="orabooks-badge">🚫 Cancelled</span>'; break;
                            default: statusBadge = '<span class="orabooks-badge">' + statusLabel + '</span>';
                        }

                        var formatIcon = exp.format === 'pdf' ? '📄' : '📊';
                        var actionsHtml = '';

                        if (exp.can_download && exp.file_url) {
                            actionsHtml += '<a href="' + exp.file_url + '" class="orabooks-btn orabooks-btn-sm orabooks-btn-primary" download target="_blank">⬇️ Download</a> ';
                        }
                        if (exp.can_cancel) {
                            actionsHtml += '<button class="orabooks-btn orabooks-btn-sm orabooks-btn-secondary orabooks-export-cancel" data-id="' + exp.id + '">❌ Cancel</button>';
                        }
                        if (exp.status === 'ready' && exp.file_hash) {
                            actionsHtml += '<span class="orabooks-nc-item-correlation" title="SHA-256: ' + exp.file_hash + '">🔒</span>';
                        }

                        html += '<tr>' +
                            '<td><strong>' + (exp.export_type || '—') + '</strong></td>' +
                            '<td>' + formatIcon + ' ' + exp.format.toUpperCase() + '</td>' +
                            '<td>' + statusBadge + '</td>' +
                            '<td>' + (exp.file_size || '—') + '</td>' +
                            '<td>' + (exp.time_remaining || exp.expires_at || '—') + '</td>' +
                            '<td>' + (exp.download_count || 0) + '</td>' +
                            '<td>' + (actionsHtml || '—') + '</td>' +
                        '</tr>';
                    });
                } else {
                    html = '<tr><td colspan="7" class="orabooks-nc-empty">📂 No exports found. Go to a report page and click "Export" to create one.</td></tr>';
                }
                $tbody.html(html);

                // Pagination
                var pagHtml = '';
                if (d.total_pages > 1) {
                    pagHtml = '<div class="orabooks-pagination-inner">';
                    for (var p = 1; p <= d.total_pages; p++) {
                        pagHtml += '<button class="orabooks-btn orabooks-btn-sm ' + (p === d.page ? 'orabooks-btn-primary' : 'orabooks-btn-secondary') + ' orabooks-export-page" data-page="' + p + '">' + p + '</button> ';
                    }
                    pagHtml += '</div>';
                }
                $('#orabooks-export-pagination').html(pagHtml);
            } else {
                $tbody.html('<tr><td colspan="7">Error loading exports.</td></tr>');
            }
        });
    }

    // Export button click — trigger request
    $(document).on('click', '.orabooks-export-trigger', function() {
        var $btn = $(this);
        var exportType = $btn.data('export-type') || 'report';
        var format = $btn.data('format') || 'csv';

        $btn.prop('disabled', true).text('⏳ Requesting...');

        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_export_request',
            export_type: exportType,
            format: format,
            parameters: JSON.stringify({})
        }, function(response) {
            if (response.error) {
                alert('Error: ' + response.message);
                $btn.prop('disabled', false).text('Export ' + format.toUpperCase());
            } else {
                $btn.text('✅ Requested').addClass('orabooks-badge-paid');
                // Refresh exports list if visible
                if ($('.orabooks-export-status').length) {
                    orabooksLoadExports(1);
                }
                // Show success message
                var msg = $('<div class="orabooks-message success" style="display:block;margin-top:8px;">✅ Export requested! <a href="?page=orabooks-exports">View status</a></div>');
                $btn.closest('td, div, p').after(msg);
                setTimeout(function() { msg.fadeOut(); }, 5000);
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Export ' + format.toUpperCase());
            alert('Network error. Please try again.');
        });
    });

    // Cancel export
    $(document).on('click', '.orabooks-export-cancel', function() {
        var $btn = $(this);
        var exportId = $btn.data('id');

        if (!confirm('Cancel this export request?')) return;

        $btn.prop('disabled', true).text('Cancelling...');

        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_export_cancel',
            export_id: exportId
        }, function(response) {
            if (response.error) {
                alert('Error: ' + response.message);
                $btn.prop('disabled', false).text('❌ Cancel');
            } else {
                orabooksLoadExports();
            }
        });
    });

    // Pagination click
    $(document).on('click', '.orabooks-export-page', function() {
        orabooksLoadExports($(this).data('page'));
    });

    // Refresh button
    $(document).on('click', '#orabooks-export-refresh', function() {
        orabooksLoadExports();
    });

    // Initialize export status page
    if ($('.orabooks-export-status').length) {
        orabooksLoadExports(1);
    }

    // =============================================
    // PARTNER DASHBOARD EXPORT (SL-114) — frontend
    // =============================================
    $(document).on('click', '.orabooks-partner-export-trigger', function() {
        var $btn = $(this);
        var origText = $btn.text();
        var exportType = $btn.data('export-type') || 'commission_data';
        var format = $btn.data('format') || 'csv';

        $btn.prop('disabled', true).text('⏳ Requesting...');

        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_export_request',
            export_type: exportType,
            format: format,
            parameters: JSON.stringify({
                columns: ['customer', 'release_month', 'amount', 'status', 'expires_at', 'earned_at']
            })
        }, function(response) {
            if (response.error) {
                alert('Error: ' + response.message);
                $btn.prop('disabled', false).text(origText);
            } else {
                $btn.text('✅ Requested').prop('disabled', true);
                var $msg = $('#orabooks-partner-export-msg');
                $msg.removeClass('error').addClass('success')
                    .html('✅ Export requested! <a href="?page=orabooks-exports">View status</a>')
                    .css('display', 'block');
                setTimeout(function() {
                    $msg.fadeOut();
                    $btn.text(origText).prop('disabled', false);
                }, 6000);
            }
        }).fail(function() {
            $btn.prop('disabled', false).text(origText);
        });
    });

    // =============================================
    // NOTIFICATION CENTER EXPORT (SL-114) — frontend
    // =============================================
    $(document).on('click', '.orabooks-notif-export-trigger', function() {
        var $btn = $(this);
        var origText = $btn.text();
        var exportType = $btn.data('export-type') || 'notification_log';
        var format = $btn.data('format') || 'csv';

        var parameters = {
            event_type: $('#orabooks-nc-filter-event').val() || '',
            priority: $('#orabooks-nc-filter-priority').val() || '',
            status: $('#orabooks-nc-filter-status').val() || ''
        };

        $btn.prop('disabled', true).text('⏳ Requesting...');

        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_export_request',
            export_type: exportType,
            format: format,
            parameters: JSON.stringify(parameters)
        }, function(response) {
            if (response.error) {
                alert('Error: ' + response.message);
                $btn.prop('disabled', false).text(origText);
            } else {
                $btn.text('✅ Requested').prop('disabled', true);
                var $msg = $('#orabooks-notif-export-msg');
                $msg.removeClass('error').addClass('success')
                    .html('✅ Export requested! <a href="?page=orabooks-exports">View status</a>')
                    .css('display', 'block');
                setTimeout(function() {
                    $msg.fadeOut();
                    $btn.text(origText).prop('disabled', false);
                }, 6000);
            }
        }).fail(function() {
            $btn.prop('disabled', false).text(origText);
        });
    });

    // Commission admin config form
    if ($('#orabooks-commission-config-form').length) {
        // Load current config
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_commission_config'
        }, function(response) {
            if (!response.error && response.data) {
                var c = response.data;
                $('#config-base-monthly').val(c.base_monthly_amount);
                $('#config-max-years').val(c.max_years);
                $('#config-yearly-pcts').val(JSON.stringify(c.yearly_percentages));
                $('#config-min-payout').val(c.min_payout_threshold);
                $('#config-active-window').val(c.customer_active_window_days);
                $('#config-expiry-action').val(c.expiry_accounting_action);
                $('#config-fee-type').val(c.payout_fee_type);
                $('#config-fee-rate').val(c.payout_fee_rate);
            }
        });
        
        // Handle form submit
        $('#orabooks-commission-config-form').on('submit', function(e) {
            e.preventDefault();
            var $msg = $('#orabooks-commission-config-message');
            $msg.hide();
            
            var formData = $(this).serializeArray();
            formData.push({ name: 'action', value: 'orabooks_commission_update_config' });
            
            $.post(orabooks_ajax.ajax_url, formData, function(response) {
                if (response.error) {
                    $msg.removeClass('success').addClass('error').text(response.message).show();
                } else {
                    $msg.removeClass('error').addClass('success').text('Configuration updated successfully').show();
                }
            });
        });
    }

    // =============================================
    // INVOICE DEEP LINK — auto-load invoice from ?invoice_id= query param
    // Used when user navigates to /dashboard/?invoice_id=N from a notification
    // =============================================
    (function() {
        var urlParams = new URLSearchParams(window.location.search);
        var invoiceId = urlParams.get('invoice_id');

        if (invoiceId && $('.orabooks-dashboard').length) {
            orabooksLoadInvoiceDetail(parseInt(invoiceId, 10));

            // Clean the invoice_id from the URL without reloading
            if (window.history.replaceState) {
                urlParams.delete('invoice_id');
                var newSearch = urlParams.toString();
                var cleanUrl = window.location.protocol + '//' +
                    window.location.host + window.location.pathname +
                    (newSearch ? '?' + newSearch : '');
                window.history.replaceState({}, document.title, cleanUrl);
            }
        }
    })();

    /**
     * Load and display invoice detail in the dashboard area.
     */
    function orabooksLoadInvoiceDetail(invoiceId) {
        var $content = $('#orabooks-dashboard-content');
        $content.html('<p class="orabooks-loading">⏳ Loading invoice...</p>');

        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_invoice_get',
            invoice_id: invoiceId
        }, function(response) {
            if (response.error) {
                $content.html(
                    '<div class="orabooks-invoice-error">' +
                        '<p>❌ ' + (response.message || 'Failed to load invoice.') + '</p>' +
                        '<a href="' + window.location.pathname + '" class="orabooks-btn orabooks-btn-secondary">← Back to Dashboard</a>' +
                    '</div>'
                );
                return;
            }

            var inv = response.data;
            var total = parseFloat(inv.total_amount || 0).toLocaleString('en-US', {style:'currency', currency: inv.currency || 'USD'});
            var paid = parseFloat(inv.paid_amount || 0).toLocaleString('en-US', {style:'currency', currency: inv.currency || 'USD'});
            var balance = parseFloat((inv.total_amount || 0) - (inv.paid_amount || 0)).toLocaleString('en-US', {style:'currency', currency: inv.currency || 'USD'});

            // Status badge HTML
            var statusMap = {
                'unpaid': '<span class="orabooks-badge orabooks-badge-pending">Unpaid</span>',
                'partial': '<span class="orabooks-badge orabooks-badge-initiated">Partial</span>',
                'paid': '<span class="orabooks-badge orabooks-badge-paid">Paid</span>',
                'overdue': '<span class="orabooks-badge orabooks-badge-expired">Overdue</span>',
                'cancelled': '<span class="orabooks-badge">Cancelled</span>'
            };
            var statusHtml = statusMap[inv.payment_status] || '<span class="orabooks-badge">' + inv.payment_status + '</span>';

            // Build payments table
            var paymentsHtml = '';
            if (inv.payments && inv.payments.length > 0) {
                paymentsHtml = '<div class="orabooks-invoice-payments">' +
                    '<h3>Payments</h3>' +
                    '<table class="orabooks-table">' +
                        '<thead><tr>' +
                            '<th>Date</th>' +
                            '<th>Amount</th>' +
                            '<th>Method</th>' +
                            '<th>Reference</th>' +
                        '</tr></thead>' +
                        '<tbody>';
                $.each(inv.payments, function(i, p) {
                    var pAmt = parseFloat(p.amount || 0).toLocaleString('en-US', {style:'currency', currency: inv.currency || 'USD'});
                    paymentsHtml += '<tr>' +
                        '<td>' + p.payment_date + '</td>' +
                        '<td>' + pAmt + '</td>' +
                        '<td>' + (p.payment_method || '—') + '</td>' +
                        '<td>' + (p.reference || '—') + '</td>' +
                    '</tr>';
                });
                paymentsHtml += '</tbody></table></div>';
            }

            var html = '<div class="orabooks-invoice-detail">' +
                '<div class="orabooks-invoice-header">' +
                    '<h2>🧾 Invoice ' + $('<span>').text(inv.invoice_number).html() + '</h2>' +
                    '<div class="orabooks-invoice-status">' + statusHtml + '</div>' +
                '</div>' +
                '<div class="orabooks-invoice-meta">' +
                    '<div class="orabooks-invoice-meta-item">' +
                        '<span class="orabooks-invoice-label">Customer</span>' +
                        '<span class="orabooks-invoice-value">' + $('<span>').text(inv.customer_email || '—').html() + '</span>' +
                    '</div>' +
                    '<div class="orabooks-invoice-meta-item">' +
                        '<span class="orabooks-invoice-label">Date</span>' +
                        '<span class="orabooks-invoice-value">' + (inv.transaction_date || '—') + '</span>' +
                    '</div>' +
                    '<div class="orabooks-invoice-meta-item">' +
                        '<span class="orabooks-invoice-label">Due Date</span>' +
                        '<span class="orabooks-invoice-value">' + (inv.due_date || '—') + '</span>' +
                    '</div>' +
                '</div>' +
                '<div class="orabooks-invoice-amounts">' +
                    '<div class="orabooks-invoice-amount-row">' +
                        '<span>Total</span>' +
                        '<span class="orabooks-invoice-amount-total">' + total + '</span>' +
                    '</div>' +
                    '<div class="orabooks-invoice-amount-row">' +
                        '<span>Paid</span>' +
                        '<span class="orabooks-invoice-amount-paid">' + paid + '</span>' +
                    '</div>' +
                    '<div class="orabooks-invoice-amount-row orabooks-invoice-amount-divider">' +
                        '<span><strong>Balance Due</strong></span>' +
                        '<span class="orabooks-invoice-amount-balance"><strong>' + balance + '</strong></span>' +
                    '</div>' +
                '</div>' +
                (inv.description ? '<div class="orabooks-invoice-description"><p>' + $('<span>').text(inv.description).html() + '</p></div>' : '') +
                paymentsHtml +
                '<div class="orabooks-invoice-actions">' +
                    '<a href="' + window.location.pathname + '" class="orabooks-btn orabooks-btn-secondary">← Back to Dashboard</a>' +
                '</div>' +
            '</div>';

            $content.html(html);
        }).fail(function() {
            $content.html(
                '<div class="orabooks-invoice-error">' +
                    '<p>❌ Network error. Could not load invoice.</p>' +
                    '<a href="' + window.location.pathname + '" class="orabooks-btn orabooks-btn-secondary">← Back to Dashboard</a>' +
                '</div>'
            );
        });
    }
});