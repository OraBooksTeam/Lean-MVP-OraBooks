/**
 * OraBooks Admin JavaScript
 */
jQuery(document).ready(function($) {
    
    // =============================================
    // DASHBOARD STATS (Live data, skeleton, auto-refresh)
    // =============================================

    /**
     * Fetch dashboard stats from server and populate all cards + panels.
     * Returns jqXHR promise so callers (e.g. refresh button) can chain off it.
     */
    window.orabooksLoadDashboard = function() {
        var $loading = $('#orabooks-dash-loading');
        var $content = $('#orabooks-dash-content');
        var $error   = $('#orabooks-dash-error');
        var $updated = $('#orabooks-last-updated');

        $loading.show();
        $content.hide();
        $error.hide();

        return $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_dashboard_stats'
        }, function(response) {
            if (response.error) {
                $loading.hide();
                $error.show();
                return;
            }

            var d = response.data;

            // --- Primary Stat Cards ---
            $('#orabooks-stat-orgs-total').text(d.organizations.total);
            $('#orabooks-stat-orgs-breakdown').html(
                '<span class="orabooks-stat-footer-item">' +
                '<span class="orabooks-dot orabooks-dot-blue"></span> Customer: ' + d.organizations.customer +
                '</span>' +
                '<span class="orabooks-stat-footer-item">' +
                '<span class="orabooks-dot orabooks-dot-purple"></span> Partner: ' + d.organizations.partner +
                '</span>'
            );
            $('#orabooks-stat-partners-active').text(d.partners.active);
            $('#orabooks-stat-partners-breakdown').html(
                '<span class="orabooks-stat-footer-item">' +
                '<span class="orabooks-dot orabooks-dot-orange"></span> Pending: ' + d.partners.pending +
                '</span>' +
                '<span class="orabooks-stat-footer-item">' +
                '<span class="orabooks-dot orabooks-dot-red"></span> Inactive: ' + d.partners.inactive +
                '</span>'
            );
            $('#orabooks-stat-users-total').text(d.users.total);
            $('#orabooks-stat-users-breakdown').html(
                '<span class="orabooks-stat-footer-item">' +
                '<span class="orabooks-dot orabooks-dot-green"></span> Verified: ' + d.users.verified +
                '</span>' +
                '<span class="orabooks-stat-footer-item">' +
                '<span class="orabooks-dot orabooks-dot-blue"></span> 2FA: ' + d.users['2fa_enabled'] +
                '</span>'
            );
            $('#orabooks-stat-attributions-verified').text(d.attributions.verified);
            $('#orabooks-stat-attributions-breakdown').html(
                '<span class="orabooks-stat-footer-item">' +
                '<span class="orabooks-dot orabooks-dot-orange"></span> Pending: ' + d.attributions.pending +
                '</span>' +
                '<span class="orabooks-stat-footer-item">' +
                '<span class="orabooks-dot orabooks-dot-red"></span> Blocked: ' + d.attributions.blocked +
                '</span>'
            );

            // --- Detail Panels ---
            if (d.organizations) {
                $('#orabooks-stat-orgs-customer').text(d.organizations.customer);
                $('#orabooks-stat-orgs-partner').text(d.organizations.partner);
                $('#orabooks-stat-orgs-active').text(d.organizations.active);
                $('#orabooks-stat-orgs-pending').text(d.organizations.pending);
                $('#orabooks-stat-orgs-suspended').text(d.organizations.suspended);
            }
            if (d.partners) {
                $('#orabooks-stat-partners-active-detail').text(d.partners.active);
                $('#orabooks-stat-partners-pending').text(d.partners.pending);
                $('#orabooks-stat-partners-inactive').text(d.partners.inactive);
                $('#orabooks-stat-partners-disabled').text(d.partners.disabled);
            }
            if (d.users) {
                $('#orabooks-stat-users-customer').text(d.users.customer);
                $('#orabooks-stat-users-partner').text(d.users.partner);
                $('#orabooks-stat-users-verified').text(d.users.verified);
                $('#orabooks-stat-users-2fa').text(d.users['2fa_enabled']);
            }
            if (d.attributions) {
                $('#orabooks-stat-attributions-total').text(d.attributions.total);
                $('#orabooks-stat-attributions-verified-detail').text(d.attributions.verified);
                $('#orabooks-stat-attributions-pending').text(d.attributions.pending);
                $('#orabooks-stat-attributions-blocked').text(d.attributions.blocked);
            }

            // Recent activity
            if (d.organizations && d.users && d.attributions) {
                $('#orabooks-stat-recent-orgs').text(d.organizations.recent_7d);
                $('#orabooks-stat-recent-users').text(d.users.recent_7d);
                $('#orabooks-stat-recent-attributions').text(d.attributions.recent_7d);
            }

            // Quick action badges
            if (d.partners) {
                $('#orabooks-qa-pending-partners').text(
                    d.partners.pending > 0 ? d.partners.pending : ''
                );
            }
            if (d.organizations) {
                $('#orabooks-qa-pending-orgs').text(
                    d.organizations.pending > 0 ? d.organizations.pending : ''
                );
            }

            // Last-updated timestamp
            if (d.timestamp) {
                $updated.text('Last updated: ' + d.timestamp);
            }

            // Animate transition
            $loading.hide();
            $content.fadeIn(300);
        }).fail(function() {
            $loading.hide();
            $error.show();
        });
    };

    // Auto-load dashboard on page load
    if ($('#orabooks-dash-loading').length) {
        orabooksLoadDashboard();
    }

    // Refresh button — resets only after AJAX completes
    $(document).on('click', '#orabooks-dash-refresh', function() {
        var $btn = $(this).text('⟳ Refreshing...').prop('disabled', true);
        var xhr = orabooksLoadDashboard();
        if (xhr && xhr.always) {
            xhr.always(function() {
                $btn.text('⟳ Refresh').prop('disabled', false);
            });
        } else {
            // Fallback: reset after 2s if no promise available
            setTimeout(function() {
                $btn.text('⟳ Refresh').prop('disabled', false);
            }, 2000);
        }
    });


    // =============================================
    
    // Load organizations list
    window.orabooksLoadOrgs = function() {
        var $tbody = $('#orabooks-orgs-table-body');
        $tbody.html('<tr><td colspan="8">Loading...</td></tr>');
        
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_list_orgs',
            type: $('#org-filter-type').val(),
            status: $('#org-filter-status').val()
        }, function(response) {
            if (!response.error && response.data) {
                var html = '';
                $.each(response.data, function(i, org) {
                    html += '<tr>' +
                        '<td>' + org.id + '</td>' +
                        '<td>' + org.name + '</td>' +
                        '<td>' + org.subdomain + '</td>' +
                        '<td>' + org.organization_type + '</td>' +
                        '<td>' + org.tier + '</td>' +
                        '<td>' + org.status + '</td>' +
                        '<td>' + org.created_at + '</td>' +
                        '<td>' +
                            '<button class="button" onclick="orabooksSuspendOrg(' + org.id + ')">Suspend</button> ' +
                            '<button class="button" onclick="orabooksActivateOrg(' + org.id + ')">Activate</button>' +
                        '</td>' +
                    '</tr>';
                });
                $tbody.html(html || '<tr><td colspan="8">No organizations found</td></tr>');
            } else {
                $tbody.html('<tr><td colspan="8">Unable to load organizations. Please refresh.</td></tr>');
            }
        }).fail(function() {
            $tbody.html('<tr><td colspan="8">Unable to load organizations. Please refresh.</td></tr>');
        });
    };

    // Load users list
    window.orabooksLoadUsers = function() {
        var $tbody = $('#orabooks-users-table-body');
        if (!$tbody.length) {
            return;
        }

        $tbody.html('<tr><td colspan="7">Loading...</td></tr>');

        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_list_users'
        }, function(response) {
            if (!response.error && response.data) {
                var html = '';
                $.each(response.data, function(i, user) {
                    html += '<tr>' +
                        '<td>' + user.id + '</td>' +
                        '<td>' + user.email + '</td>' +
                        '<td>' + (user.is_partner ? 'Partner' : 'Customer') + '</td>' +
                        '<td>' + (user.is_email_verified ? 'Yes' : 'No') + '</td>' +
                        '<td>' + (user.is_2fa_enabled ? 'Yes' : 'No') + '</td>' +
                        '<td>' + (user.org_id || '—') + '</td>' +
                        '<td>' + (user.created_at || '—') + '</td>' +
                    '</tr>';
                });
                $tbody.html(html || '<tr><td colspan="7">No users found</td></tr>');
            } else {
                $tbody.html('<tr><td colspan="7">Unable to load users. Please refresh.</td></tr>');
            }
        }).fail(function() {
            $tbody.html('<tr><td colspan="7">Unable to load users. Please refresh.</td></tr>');
        });
    };
    
    // Load audit logs
    window.orabooksLoadAuditLogs = function() {
        var $tbody = $('#orabooks-audit-table-body');
        $tbody.html('<tr><td colspan="7">Loading...</td></tr>');
        
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_get_audit_logs',
            event_type: $('#audit-filter-event').val(),
            user_id: $('#audit-filter-user').val(),
            from_date: $('#audit-filter-from').val(),
            to_date: $('#audit-filter-to').val()
        }, function(response) {
            if (!response.error && response.data) {
                var html = '';
                $.each(response.data, function(i, log) {
                    html += '<tr>' +
                        '<td>' + log.created_at + '</td>' +
                        '<td>' + log.user_id + '</td>' +
                        '<td>' + log.event_type + '</td>' +
                        '<td>' + log.severity + '</td>' +
                        '<td>' + log.description + '</td>' +
                        '<td>' + log.ip_address + '</td>' +
                        '<td>' + log.correlation_id + '</td>' +
                    '</tr>';
                });
                $tbody.html(html || '<tr><td colspan="7">No logs found</td></tr>');
            } else {
                $tbody.html('<tr><td colspan="7">Unable to load audit logs. Please refresh.</td></tr>');
            }
        }).fail(function() {
            $tbody.html('<tr><td colspan="7">Unable to load audit logs. Please refresh.</td></tr>');
        });
    };
    
    // Export audit logs
    window.orabooksExportAuditLogs = function() {
        window.location.href = orabooks_ajax.ajax_url + 
            '?action=orabooks_export_audit_logs' +
            '&event_type=' + $('#audit-filter-event').val() +
            '&user_id=' + $('#audit-filter-user').val() +
            '&from_date=' + $('#audit-filter-from').val() +
            '&to_date=' + $('#audit-filter-to').val();
    };
    
    // Suspend organization
    window.orabooksSuspendOrg = function(orgId) {
        if (!confirm('Are you sure you want to suspend this organization?')) return;
        
        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_suspend_org',
            org_id: orgId
        }, function(response) {
            if (!response.error) {
                alert('Organization suspended');
                orabooksLoadOrgs();
            } else {
                alert(response.message);
            }
        });
    };
    
    // Activate organization
    window.orabooksActivateOrg = function(orgId) {
        if (!confirm('Activate this organization?')) return;
        
        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_activate_org',
            org_id: orgId
        }, function(response) {
            if (!response.error) {
                alert('Organization activated');
                orabooksLoadOrgs();
            } else {
                alert(response.message);
            }
        });
    };
    
    // =============================================
    // CHART OF ACCOUNTS (Admin)
    // =============================================

    // Load organizations into CoA dropdown
    window.orabooksLoadCoAOrgs = function() {
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_list_orgs'
        }, function(response) {
            if (!response.error && response.data) {
                var $select = $('#coa-org-select');
                $.each(response.data, function(i, org) {
                    $select.append('<option value="' + org.id + '">' + escHtml(org.name) + ' (' + escHtml(org.subdomain) + ')</option>');
                });
            }
        });
    };

    // Load chart of accounts
    window.orabooksLoadCoA = function() {
        var orgId = $('#coa-org-select').val();
        var typeFilter = $('#coa-filter-type').val();

        if (!orgId) {
            $('#orabooks-coa-table-body').html('<tr><td colspan="6">Please select an organization.</td></tr>');
            return;
        }

        var $tbody = $('#orabooks-coa-table-body');
        $tbody.html('<tr><td colspan="6">Loading...</td></tr>');

        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_get_coa',
            org_id: orgId
        }, function(response) {
            if (!response.error && response.data) {
                var html = '';
                $.each(response.data, function(i, acc) {
                    if (typeFilter && acc.type !== typeFilter) return;
                    html += '<tr>' +
                        '<td><strong>' + escHtml(acc.code) + '</strong></td>' +
                        '<td>' + escHtml(acc.name) + '</td>' +
                        '<td><span class="orabooks-coa-type orabooks-coa-type-' + acc.type + '">' + escHtml(acc.type) + '</span></td>' +
                        '<td>' + escHtml(acc.normal_balance) + '</td>' +
                        '<td>' + (parseInt(acc.system_generated) ? '✅' : '—') + '</td>' +
                        '<td>' + (parseInt(acc.is_active) ? '✅ Active' : '❌ Inactive') + '</td>' +
                    '</tr>';
                });
                $tbody.html(html || '<tr><td colspan="6">No accounts found for this organization.</td></tr>');
            } else {
                $tbody.html('<tr><td colspan="6">Error loading accounts.</td></tr>');
            }
        });
    };

    // CoA export trigger — passes org_id as parameter
    $(document).on('click', '.orabooks-coa-export-trigger', function() {
        var $btn = $(this);
        var exportType = $btn.data('export-type') || 'coa';
        var format = $btn.data('format') || 'csv';
        var orgId = $('#coa-org-select').val();

        if (!orgId) {
            alert('Please select an organization first.');
            return;
        }

        $btn.prop('disabled', true).text('⏳ Requesting...');

        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_export_request',
            export_type: exportType,
            format: format,
            parameters: JSON.stringify({
                org_id: parseInt(orgId),
                columns: ['code', 'name', 'type', 'normal_balance', 'system_generated', 'is_active']
            })
        }, function(response) {
            if (response.error) {
                alert('Error: ' + response.message);
                $btn.prop('disabled', false).text(format === 'csv' ? 'Export CSV (Async)' : 'Export PDF (Async)');
            } else {
                $btn.text('✅ Requested').prop('disabled', true);
                $('#orabooks-coa-export-msg').removeClass('error').addClass('success')
                    .html('✅ Export requested! <a href="admin.php?page=orabooks-exports">View My Exports</a>')
                    .css('display', 'block');
                setTimeout(function() {
                    $('#orabooks-coa-export-msg').fadeOut();
                    $btn.text(format === 'csv' ? 'Export CSV (Async)' : 'Export PDF (Async)').prop('disabled', false);
                }, 5000);
            }
        }).fail(function() {
            $btn.prop('disabled', false).text(format === 'csv' ? 'Export CSV (Async)' : 'Export PDF (Async)');
            alert('Network error. Please try again.');
        });
    });

    // =============================================
    // AUDIT LOG EXPORT (SL-114) — override export-trigger to pass current filters
    // =============================================
    $(document).on('click', '.orabooks-export-trigger', function() {
        var $btn = $(this);
        var exportType = $btn.data('export-type') || 'audit_log';
        var format = $btn.data('format') || 'csv';

        // Collect current filters from the audit page
        var parameters = {
            event_type: $('#audit-filter-event').val() || '',
            user_id: $('#audit-filter-user').val() || '',
            from_date: $('#audit-filter-from').val() || '',
            to_date: $('#audit-filter-to').val() || ''
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
                $btn.prop('disabled', false).text(
                    format === 'csv' ? 'Export CSV (Async)' : 'Export PDF (Async)'
                );
            } else {
                $btn.text('✅ Requested').prop('disabled', true);
                // Show success message below filters
                var $msg = $('<div class="notice notice-success inline" style="margin:8px 0;padding:8px 12px;">' +
                    '<p>✅ Export requested! <a href="admin.php?page=orabooks-exports">View My Exports</a></p></div>');
                $btn.closest('.orabooks-filters').after($msg);
                setTimeout(function() {
                    $msg.fadeOut(function() { $(this).remove(); });
                    $btn.text(format === 'csv' ? 'Export CSV (Async)' : 'Export PDF (Async)').prop('disabled', false);
                }, 5000);
            }
        }).fail(function() {
            $btn.prop('disabled', false).text(format === 'csv' ? 'Export CSV (Async)' : 'Export PDF (Async)');
        });
    });

    /**
     * Escape HTML to prevent XSS
     */
    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // =============================================
    // PARTNER DASHBOARD EXPORT (SL-114)
    // =============================================
    $(document).on('click', '.orabooks-partner-export-trigger', function() {
        var $btn = $(this);
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
                $btn.prop('disabled', false).text(
                    format === 'csv' ? 'Export Commissions CSV' : 'Export Commissions PDF'
                );
            } else {
                $btn.text('✅ Requested').prop('disabled', true);
                var $msg = $('#orabooks-partner-export-msg');
                $msg.removeClass('error').addClass('success')
                    .html('✅ Export requested! <a href="admin.php?page=orabooks-exports">View My Exports</a>')
                    .css('display', 'block');
                setTimeout(function() {
                    $msg.fadeOut();
                    $btn.text(format === 'csv' ? 'Export Commissions CSV' : 'Export Commissions PDF').prop('disabled', false);
                }, 6000);
            }
        }).fail(function() {
            $btn.prop('disabled', false).text(
                format === 'csv' ? 'Export Commissions CSV' : 'Export Commissions PDF'
            );
        });
    });

    // =============================================
    // NOTIFICATION CENTER EXPORT (SL-114)
    // =============================================
    $(document).on('click', '.orabooks-notif-export-trigger', function() {
        var $btn = $(this);
        var exportType = $btn.data('export-type') || 'notification_log';
        var format = $btn.data('format') || 'csv';

        // Collect current notification filters
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
                $btn.prop('disabled', false).text(
                    format === 'csv' ? 'Export CSV' : 'Export PDF'
                );
            } else {
                $btn.text('✅ Requested').prop('disabled', true);
                var $msg = $('#orabooks-notif-export-msg');
                $msg.removeClass('error').addClass('success')
                    .html('✅ Export requested! <a href="admin.php?page=orabooks-exports">View My Exports</a>')
                    .css('display', 'block');
                setTimeout(function() {
                    $msg.fadeOut();
                    $btn.text(format === 'csv' ? 'Export CSV' : 'Export PDF').prop('disabled', false);
                }, 6000);
            }
        }).fail(function() {
            $btn.prop('disabled', false).text(
                format === 'csv' ? 'Export CSV' : 'Export PDF'
            );
        });
    });

    // =============================================
    // ASYNC QUEUE DASHBOARD EXPORT (SL-114)
    // =============================================
    $(document).on('click', '.orabooks-aq-export-trigger', function() {
        var $btn = $(this);
        var origText = $btn.text().trim();
        var exportType = $btn.data('export-type') || 'async_queue_data';
        var format = $btn.data('format') || 'csv';

        $btn.prop('disabled', true).text('⏳ Requesting...');

        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_export_request',
            export_type: exportType,
            format: format,
            parameters: JSON.stringify({
                columns: ['status', 'count', 'job_type', 'queue_name', 'priority', 'latency', 'failure_rate']
            })
        }, function(response) {
            if (response.error) {
                alert('Error: ' + response.message);
                $btn.prop('disabled', false).text(origText);
            } else {
                $btn.text('✅ Requested').prop('disabled', true);
                var $msg = $('#orabooks-aq-export-msg');
                $msg.removeClass('error').addClass('success')
                    .html('✅ Export requested! <a href="admin.php?page=orabooks-exports">View My Exports</a>')
                    .css('display', 'block');
                setTimeout(function() {
                    $msg.fadeOut();
                    $btn.text(origText).prop('disabled', false);
                }, 5000);
            }
        }).fail(function() {
            $btn.prop('disabled', false).text(origText);
        });
    });

    // =============================================
    // USERS & TEAMS EXPORT (SL-114)
    // =============================================
    $(document).on('click', '.orabooks-users-export-trigger', function() {
        var $btn = $(this);
        var origText = $btn.text().trim();
        var exportType = $btn.data('export-type') || 'users_data';
        var format = $btn.data('format') || 'csv';

        $btn.prop('disabled', true).text('⏳ Requesting...');

        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_export_request',
            export_type: exportType,
            format: format,
            parameters: JSON.stringify({
                columns: ['id', 'email', 'is_active', 'is_email_verified', 'is_2fa_enabled', 'auth_provider', 'org_id', 'is_partner', 'created_at']
            })
        }, function(response) {
            if (response.error) {
                alert('Error: ' + response.message);
                $btn.prop('disabled', false).text(origText);
            } else {
                $btn.text('✅ Requested').prop('disabled', true);
                var $msg = $('#orabooks-users-export-msg');
                $msg.removeClass('error').addClass('success')
                    .html('✅ Export requested! <a href="admin.php?page=orabooks-exports">View My Exports</a>')
                    .css('display', 'block');
                setTimeout(function() {
                    $msg.fadeOut();
                    $btn.text(origText).prop('disabled', false);
                }, 5000);
            }
        }).fail(function() {
            $btn.prop('disabled', false).text(origText);
        });
    });

    // =============================================
    // COMMISSION CONFIG EXPORT (SL-114)
    // =============================================
    $(document).on('click', '.orabooks-commconfig-export-trigger', function() {
        var $btn = $(this);
        var origText = $btn.text().trim();
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
                    .html('✅ Export requested! <a href="admin.php?page=orabooks-exports">View My Exports</a>')
                    .css('display', 'block');
                setTimeout(function() {
                    $msg.fadeOut();
                    $btn.text(origText).prop('disabled', false);
                }, 5000);
            }
        }).fail(function() {
            $btn.prop('disabled', false).text(origText);
        });
    });

    // =============================================
    // PARTNER ONBOARDING EXPORT (SL-114)
    // =============================================
    $(document).on('click', '.orabooks-onboarding-export-trigger', function() {
        var $btn = $(this);
        var origText = $btn.text().trim();
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
                    .html('✅ Export requested! <a href="admin.php?page=orabooks-exports">View My Exports</a>')
                    .css('display', 'block');
                setTimeout(function() {
                    $msg.fadeOut();
                    $btn.text(origText).prop('disabled', false);
                }, 5000);
            }
        }).fail(function() {
            $btn.prop('disabled', false).text(origText);
        });
    });

    // =============================================
    // FRONTEND EXPORT BUTTON HANDLER (for shortcode pages in admin context)
    // =============================================
    // The frontend.js handles .orabooks-export-trigger for frontend pages.
    // When these shortcodes are rendered in the admin, admin.js handles them.
    // The admin audit page override already handles .orabooks-export-trigger for audit exports.

    // =============================================
    // SL-003: PARTNER MANAGEMENT ADMIN
    // =============================================

    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.orabooks-admin-tab-content').hide();
        $('#orabooks-tab-' + tab).show();
    });

    // Load pending partners
    window.orabooksLoadPendingPartners = function() {
        var $tbody = $('#orabooks-pending-partners-body');
        $tbody.html('<tr><td colspan="8">Loading...</td></tr>');
        
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_admin_list_pending_partners'
        }, function(response) {
            if (!response.error && response.data) {
                var html = '';
                $.each(response.data, function(i, p) {
                    html += '<tr>' +
                        '<td>' + p.id + '</td>' +
                        '<td><strong>' + escHtml(p.partner_code) + '</strong></td>' +
                        '<td>' + escHtml(p.email) + '</td>' +
                        '<td>' + escHtml(p.partner_type) + '</td>' +
                        '<td>' + escHtml(p.org_name || '—') + '</td>' +
                        '<td>' + escHtml(p.org_status) + '</td>' +
                        '<td>' + p.created_at + '</td>' +
                        '<td>' +
                            '<button class="button button-primary orabooks-approve-btn" data-id="' + p.id + '" style="margin-right:4px;">Approve</button>' +
                            '<button class="button orabooks-reject-btn" data-id="' + p.id + '">Reject</button>' +
                        '</td>' +
                    '</tr>';
                });
                
                var count = response.data.length;
                $('#orabooks-pending-count').text(count > 0 ? count : '');
                $tbody.html(html || '<tr><td colspan="8">No pending partner codes to review.</td></tr>');
            } else {
                $tbody.html('<tr><td colspan="8">Error loading partners.</td></tr>');
            }
        });
    };

    // Load reactivation requests
    window.orabooksLoadReactivationRequests = function() {
        var $tbody = $('#orabooks-reactivation-partners-body');
        $tbody.html('<tr><td colspan="7">Loading...</td></tr>');
        
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_admin_list_reactivation_requests'
        }, function(response) {
            if (!response.error && response.data) {
                var html = '';
                $.each(response.data, function(i, r) {
                    html += '<tr>' +
                        '<td>' + r.id + '</td>' +
                        '<td><strong>' + escHtml(r.partner_code || '—') + '</strong></td>' +
                        '<td>' + escHtml(r.requested_by_email) + '</td>' +
                        '<td>' + escHtml(r.org_name) + ' (' + escHtml(r.subdomain) + ')</td>' +
                        '<td>' + escHtml(r.reason) + '</td>' +
                        '<td>' + r.requested_at + '</td>' +
                        '<td>' +
                            '<button class="button button-primary orabooks-reactivation-approve-btn" data-id="' + r.id + '" style="margin-right:4px;">Approve</button>' +
                            '<button class="button orabooks-reactivation-deny-btn" data-id="' + r.id + '">Deny</button>' +
                        '</td>' +
                    '</tr>';
                });
                
                var count = response.data.length;
                $('#orabooks-reactivation-count').text(count > 0 ? count : '');
                $tbody.html(html || '<tr><td colspan="7">No pending reactivation requests.</td></tr>');
            } else {
                $tbody.html('<tr><td colspan="7">Error loading reactivation requests.</td></tr>');
            }
        });
    };

    // Load active partners
    window.orabooksLoadActivePartners = function() {
        var $tbody = $('#orabooks-active-partners-body');
        $tbody.html('<tr><td colspan="8">Loading...</td></tr>');
        
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_admin_list_active_partners'
        }, function(response) {
            if (!response.error && response.data) {
                var html = '';
                $.each(response.data, function(i, p) {
                    html += '<tr>' +
                        '<td>' + p.id + '</td>' +
                        '<td><strong>' + escHtml(p.partner_code) + '</strong></td>' +
                        '<td>' + escHtml(p.email) + '</td>' +
                        '<td>' + escHtml(p.partner_type) + '</td>' +
                        '<td>' + escHtml(p.org_name || '—') + '</td>' +
                        '<td>' + (p.verified_attributions || 0) + '</td>' +
                        '<td>' + (p.approved_at || '—') + '</td>' +
                        '<td>' + (p.last_attribution_at || '—') + '</td>' +
                    '</tr>';
                });
                $tbody.html(html || '<tr><td colspan="8">No active partners found.</td></tr>');
            } else {
                $tbody.html('<tr><td colspan="8">Error loading partners.</td></tr>');
            }
        });
    };

    // Approve partner code
    $(document).on('click', '.orabooks-approve-btn', function() {
        var $btn = $(this);
        var partnerCodeId = $btn.data('id');
        
        if (!confirm('Approve this partner code and activate the organization?')) return;
        
        $btn.prop('disabled', true).text('Approving...');
        
        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_admin_approve_partner',
            partner_code_id: partnerCodeId
        }, function(response) {
            if (!response.error) {
                alert('Partner approved successfully.');
                orabooksLoadPendingPartners();
                orabooksLoadActivePartners();
            } else {
                alert(response.message);
                $btn.prop('disabled', false).text('Approve');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Approve');
        });
    });

    // Show reject modal
    var rejectTargetId = null;
    $(document).on('click', '.orabooks-reject-btn', function() {
        rejectTargetId = $(this).data('id');
        $('#orabooks-reject-reason').val('');
        $('#orabooks-reject-message').hide();
        $('#orabooks-reject-modal').show();
    });

    $('#orabooks-reject-cancel').on('click', function() {
        $('#orabooks-reject-modal').hide();
        rejectTargetId = null;
    });

    $('#orabooks-reject-confirm').on('click', function() {
        if (!rejectTargetId) return;
        
        var $btn = $(this);
        var reason = $('#orabooks-reject-reason').val();
        
        $btn.prop('disabled', true).text('Rejecting...');
        
        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_admin_reject_partner',
            partner_code_id: rejectTargetId,
            reason: reason
        }, function(response) {
            if (!response.error) {
                alert('Partner code rejected.');
                $('#orabooks-reject-modal').hide();
                rejectTargetId = null;
                orabooksLoadPendingPartners();
            } else {
                $('#orabooks-reject-message').addClass('error').text(response.message).show();
                $btn.prop('disabled', false).text('Confirm Reject');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Confirm Reject');
        });
    });

    // Approve reactivation
    $(document).on('click', '.orabooks-reactivation-approve-btn', function() {
        var $btn = $(this);
        var reviewId = $btn.data('id');
        
        if (!confirm('Approve this reactivation request?')) return;
        
        $btn.prop('disabled', true).text('Approving...');
        
        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_admin_review_reactivation',
            review_id: reviewId,
            decision: 'approved',
            notes: 'Approved by admin'
        }, function(response) {
            if (!response.error) {
                alert('Reactivation approved.');
                orabooksLoadReactivationRequests();
                orabooksLoadActivePartners();
            } else {
                alert(response.message);
                $btn.prop('disabled', false).text('Approve');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Approve');
        });
    });

    // Deny reactivation
    $(document).on('click', '.orabooks-reactivation-deny-btn', function() {
        var $btn = $(this);
        var reviewId = $btn.data('id');
        var notes = prompt('Reason for denial:');
        
        if (notes === null) return; // Cancelled
        
        $btn.prop('disabled', true).text('Denying...');
        
        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_admin_review_reactivation',
            review_id: reviewId,
            decision: 'denied',
            notes: notes || 'Denied by admin'
        }, function(response) {
            if (!response.error) {
                alert('Reactivation denied.');
                orabooksLoadReactivationRequests();
            } else {
                alert(response.message);
                $btn.prop('disabled', false).text('Deny');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Deny');
        });
    });

    // Auto-load partner tables on page load
    if ($('#orabooks-pending-partners-body').length) {
        orabooksLoadPendingPartners();
        orabooksLoadActivePartners();
        orabooksLoadReactivationRequests();
    }

    // =============================================
    // SL-021: CUSTOMERS & INVOICES ADMIN
    // =============================================

    var custCurrentTab = 'customers';

    // Init: load stats on page load
    if ($('#orabooks-customers-tbody').length) {
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_customer_stats',
            org_id: 0
        }, function(r) {
            if (r.success !== false) {
                orabooksLoadCustomers();
                orabooksLoadReports(r.data);
            }
        });
    }

    // Tab switching
    $(document).on('click', '#orabooks-cust-tabs .nav-tab', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        custCurrentTab = tab;

        $('#orabooks-cust-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.orabooks-tab-content').hide();
        $('#orabooks-tab-' + tab).fadeIn(200);

        if (tab === 'invoices') orabooksLoadInvoices();
        if (tab === 'reports') orabooksLoadReports();
    });

    // ==================== CUSTOMERS ====================
    window.orabooksLoadCustomers = function() {
        var $loading = $('#orabooks-cust-loading').show();
        var $content = $('#orabooks-customers-content').hide();

        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_customer_stats',
            org_id: 0
        }, function(r) {
            $('#orabooks-cust-updated').text(new Date().toLocaleTimeString());
            $loading.hide();
            $content.fadeIn(200);

            if (r.data) {
                window.orabooksRenderCustomerStats(r.data);
            }
        });

        orabooksLoadCustomerList();
    };

    window.orabooksRenderCustomerStats = function(stats) {
        $('#orabooks-cust-total .orabooks-stat-number').text(stats.total_customers || 0);
        $('#orabooks-cust-active .orabooks-stat-number').text(stats.active_customers || 0);
        $('#orabooks-cust-inactive .orabooks-stat-number').text(stats.inactive_customers || 0);
        $('#orabooks-cust-revenue .orabooks-stat-number').text('$' + (parseFloat(stats.total_revenue || 0)).toLocaleString('en-US', {minimumFractionDigits: 2}));
    };

    window.orabooksLoadCustomerList = function() {
        var isActive = $('#orabooks-cust-filter-active').val();
        var search = $('#orabooks-cust-search').val();

        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_customers_list',
            org_id: 0,
            is_active: isActive || null,
            search: search,
            limit: 100,
            offset: 0
        }, function(r) {
            var tbody = $('#orabooks-customers-tbody');
            tbody.empty();

            if (!r.data || !r.data.customers || r.data.customers.length === 0) {
                tbody.html('<tr><td colspan="7">No customers found.</td></tr>');
                return;
            }

            $.each(r.data.customers, function(i, c) {
                var statusBadge = c.is_active == 1
                    ? '<span class="orabooks-badge orabooks-badge-active">Active</span>'
                    : '<span class="orabooks-badge orabooks-badge-inactive">Inactive</span>';

                var totalPaid = parseFloat(c.total_paid || 0).toLocaleString('en-US', {style:'currency', currency:'USD'});

                tbody.append('<tr>' +
                    '<td>' + c.id + '</td>' +
                    '<td><a href="#" class="orabooks-cust-view" data-id="' + c.id + '">' + $('<span>').text(c.email).html() + '</a></td>' +
                    '<td>' + statusBadge + '</td>' +
                    '<td>' + (c.invoice_count || 0) + '</td>' +
                    '<td>' + totalPaid + '</td>' +
                    '<td>' + (c.last_paid_invoice_date || '—') + '</td>' +
                    '<td>' +
                        '<button class="button button-small orabooks-cust-toggle-active" data-id="' + c.id + '" data-active="' + c.is_active + '">' +
                            (c.is_active == 1 ? 'Deactivate' : 'Activate') +
                        '</button> ' +
                        '<button class="button button-small orabooks-cust-view" data-id="' + c.id + '">View</button>' +
                    '</td>' +
                    '</tr>');
            });
        });
    };

    // Filter customers
    $(document).on('change', '#orabooks-cust-filter-active', function() { orabooksLoadCustomerList(); });
    $(document).on('click', '#orabooks-cust-refresh-btn', function() { orabooksLoadCustomerList(); });

    // Search on enter
    $(document).on('keydown', '#orabooks-cust-search', function(e) {
        if (e.keyCode === 13) orabooksLoadCustomerList();
    });

    // Toggle active status
    $(document).on('click', '.orabooks-cust-toggle-active', function() {
        var $btn = $(this);
        var customerId = $btn.data('id');
        var isActive = $btn.data('active') == 1 ? 0 : 1;

        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_customer_update',
            customer_id: customerId,
            is_active: isActive
        }, function(r) {
            if (r.error !== false && r.error !== true) return;
            orabooksLoadCustomerList();
        });
    });

    // View customer detail
    $(document).on('click', '.orabooks-cust-view', function(e) {
        e.preventDefault();
        var customerId = $(this).data('id');
        window.orabooksShowCustomerDetail(customerId);
    });

    window.orabooksShowCustomerDetail = function(customerId) {
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_customer_get',
            customer_id: customerId
        }, function(r) {
            if (r.error !== false && r.error !== true) return;
            var c = r.data;
            var html = '<table class="form-table">' +
                '<tr><th>ID</th><td>' + c.id + '</td></tr>' +
                '<tr><th>Email</th><td>' + $('<span>').text(c.email).html() + '</td></tr>' +
                '<tr><th>Status</th><td>' + (c.is_active == 1 ? '<span class="orabooks-badge orabooks-badge-active">Active</span>' : '<span class="orabooks-badge orabooks-badge-inactive">Inactive</span>') + '</td></tr>' +
                '<tr><th>Last Payment</th><td>' + (c.last_paid_invoice_date || '—') + '</td></tr>' +
                '<tr><th>Lifetime Value</th><td>' + parseFloat(c.lifetime_value || 0).toLocaleString('en-US', {style:'currency', currency:'USD'}) + '</td></tr>' +
                '<tr><th>Verified</th><td>' + (c.is_email_verified == 1 ? 'Yes' : 'No') + '</td></tr>' +
                '<tr><th>Notes</th><td><textarea id="orabooks-cust-notes" rows="3" style="width:100%;">' + (c.notes || '') + '</textarea>' +
                '<p><button class="button orabooks-cust-save-notes" data-id="' + c.id + '">Save Notes</button></p></td></tr>' +
                '</table>';

            $('#orabooks-cust-detail-title').text('Customer: ' + c.email);
            $('#orabooks-cust-detail-body').html(html);
            $('#orabooks-customer-detail').fadeIn(200);
        });
    };

    $(document).on('click', '#orabooks-cust-detail-close', function() {
        $('#orabooks-customer-detail').fadeOut(200);
    });

    $(document).on('click', '.orabooks-cust-save-notes', function() {
        var customerId = $(this).data('id');
        var notes = $('#orabooks-cust-notes').val();

        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_customer_update',
            customer_id: customerId,
            notes: notes
        }, function(r) {
            alert('Notes saved.');
        });
    });

    // ==================== INVOICES ====================
    window.orabooksLoadInvoices = function() {
        var status = $('#orabooks-inv-filter-status').val();
        var workflow = $('#orabooks-inv-filter-workflow').val();
        var from = $('#orabooks-inv-filter-from').val();
        var to = $('#orabooks-inv-filter-to').val();

        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_invoices_list',
            org_id: 0,
            payment_status: status,
            workflow_status: workflow,
            from_date: from,
            to_date: to,
            limit: 100,
            offset: 0
        }, function(r) {
            var tbody = $('#orabooks-invoices-tbody');
            tbody.empty();

            if (!r.data || !r.data.invoices || r.data.invoices.length === 0) {
                tbody.html('<tr><td colspan="8">No invoices found.</td></tr>');
                return;
            }

            $.each(r.data.invoices, function(i, inv) {
                var pct = inv.total_amount > 0 ? Math.round((inv.total_paid_amount / inv.total_amount) * 100) : 0;
                var statusHtml = window.orabooksGetInvoiceStatusHtml(inv.payment_status);
                var total = parseFloat(inv.total_amount || 0).toLocaleString('en-US', {style:'currency', currency:'USD'});
                var paid = parseFloat(inv.total_paid_amount || 0).toLocaleString('en-US', {style:'currency', currency:'USD'});

                tbody.append('<tr>' +
                    '<td><a href="#" class="orabooks-inv-view" data-id="' + inv.id + '">' + $('<span>').text(inv.invoice_number).html() + '</a></td>' +
                    '<td>' + $('<span>').text(inv.customer_email).html() + '</td>' +
                    '<td>' + inv.transaction_date + '</td>' +
                    '<td>' + inv.due_date + '</td>' +
                    '<td>' + total + '</td>' +
                    '<td>' + paid + ' <span class="orabooks-inv-progress">(' + pct + '%)</span></td>' +
                    '<td>' + statusHtml + '</td>' +
                    '<td>' +
                        (inv.payment_status !== 'paid' && inv.payment_status !== 'cancelled'
                            ? '<button class="button button-small orabooks-inv-pay" data-id="' + inv.id + '" data-number="' + $('<span>').text(inv.invoice_number).html() + '">Pay</button> '
                            : '') +
                        '<button class="button button-small orabooks-inv-view" data-id="' + inv.id + '">View</button>' +
                    '</td>' +
                    '</tr>');
            });
        });
    };

    window.orabooksGetInvoiceStatusHtml = function(status) {
        var map = {
            'unpaid': ['orabooks-badge-warning', 'Unpaid'],
            'partial': ['orabooks-badge-info', 'Partial'],
            'paid': ['orabooks-badge-active', 'Paid'],
            'overdue': ['orabooks-badge-danger', 'Overdue'],
            'cancelled': ['orabooks-badge-inactive', 'Cancelled']
        };
        var m = map[status] || ['orabooks-badge-warning', status];
        return '<span class="orabooks-badge ' + m[0] + '">' + m[1] + '</span>';
    };

    // Invoice filters
    $(document).on('click', '#orabooks-inv-filter-btn', function() { orabooksLoadInvoices(); });

    // Create Invoice Modal
    $(document).on('click', '#orabooks-inv-create-btn', function() {
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_customers_list',
            org_id: 0,
            limit: 500,
            offset: 0
        }, function(r) {
            var sel = $('#inv_customer_id');
            sel.empty().append('<option value="">Select customer...</option>');
            if (r.data && r.data.customers) {
                $.each(r.data.customers, function(i, c) {
                    sel.append('<option value="' + c.id + '">' + $('<span>').text(c.email).html() + '</option>');
                });
            }
        });

        $('#inv_invoice_date').val(new Date().toISOString().slice(0, 10));
        $('#orabooks-invoice-modal').fadeIn(200);
    });

    // Submit create invoice
    $(document).on('submit', '#orabooks-invoice-form', function(e) {
        e.preventDefault();
        var data = $(this).serialize();
        var orgId = $('#inv_org_id').val() || 0;
        data += '&action=orabooks_invoice_create&org_id=' + orgId;

        $.post(orabooks_ajax.ajax_url, data, function(r) {
            if (r.error !== false && r.error !== true) return;

            $('#orabooks-invoice-modal').fadeOut(200);
            $('#orabooks-invoice-form')[0].reset();
            orabooksLoadInvoices();
            alert('Invoice created successfully!');
        }).fail(function() {
            alert('Failed to create invoice.');
        });
    });

    // View Invoice Detail
    $(document).on('click', '.orabooks-inv-view', function(e) {
        e.preventDefault();
        var invoiceId = $(this).data('id');
        window.orabooksShowInvoiceDetail(invoiceId);
    });

    window.orabooksShowInvoiceDetail = function(invoiceId) {
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_invoice_get',
            invoice_id: invoiceId
        }, function(r) {
            if (r.error !== false && r.error !== true) return;
            var inv = r.data;
            var total = parseFloat(inv.total_amount || 0).toLocaleString('en-US', {style:'currency', currency:'USD'});
            var paid = parseFloat(inv.paid_amount || 0).toLocaleString('en-US', {style:'currency', currency:'USD'});

            var paymentsHtml = '';
            if (inv.payments && inv.payments.length > 0) {
                paymentsHtml = '<h4>Payments</h4><table class="wp-list-table widefat fixed striped"><thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th></tr></thead><tbody>';
                $.each(inv.payments, function(i, p) {
                    paymentsHtml += '<tr><td>' + p.payment_date + '</td><td>' + parseFloat(p.amount).toLocaleString('en-US', {style:'currency', currency:'USD'}) + '</td><td>' + p.payment_method + '</td><td>' + (p.reference || '—') + '</td></tr>';
                });
                paymentsHtml += '</tbody></table>';
            }

            var html = '<table class="form-table">' +
                '<tr><th>Invoice #</th><td><strong>' + $('<span>').text(inv.invoice_number).html() + '</strong></td></tr>' +
                '<tr><th>Customer</th><td>' + $('<span>').text(inv.customer_email).html() + '</td></tr>' +
                '<tr><th>Date</th><td>' + inv.transaction_date + '</td></tr>' +
                '<tr><th>Due Date</th><td>' + inv.due_date + '</td></tr>' +
                '<tr><th>Total</th><td><strong>' + total + '</strong></td></tr>' +
                '<tr><th>Paid</th><td>' + paid + '</td></tr>' +
                '<tr><th>Balance Due</th><td><strong>' + parseFloat(inv.total_amount - inv.paid_amount).toLocaleString('en-US', {style:'currency', currency:'USD'}) + '</strong></td></tr>' +
                '<tr><th>Status</th><td>' + window.orabooksGetInvoiceStatusHtml(inv.payment_status) + '</td></tr>' +
                '<tr><th>Description</th><td>' + (inv.description || '—') + '</td></tr>' +
                '</table>' +
                paymentsHtml;

            $('#orabooks-inv-detail-title').text('Invoice: ' + inv.invoice_number);
            $('#orabooks-inv-detail-body').html(html);
            $('#orabooks-invoice-detail').fadeIn(200);
        });
    };

    $(document).on('click', '#orabooks-inv-detail-close', function() {
        $('#orabooks-invoice-detail').fadeOut(200);
    });

    // Record Payment Modal
    $(document).on('click', '.orabooks-inv-pay', function() {
        var invoiceId = $(this).data('id');
        var invoiceNumber = $(this).data('number');
        $('#pay_invoice_id').val(invoiceId);
        $('#pay_invoice_number').text(invoiceNumber);
        $('#pay_date').val(new Date().toISOString().slice(0, 10));
        $('#orabooks-payment-modal').fadeIn(200);
    });

    // Submit payment
    $(document).on('submit', '#orabooks-payment-form', function(e) {
        e.preventDefault();
        var data = $(this).serialize();
        var orgId = $('#pay_org_id').val() || 0;
        data += '&action=orabooks_invoice_record_payment&org_id=' + orgId;

        $.post(orabooks_ajax.ajax_url, data, function(r) {
            if (r.error !== false && r.error !== true) return;

            $('#orabooks-payment-modal').fadeOut(200);
            $('#orabooks-payment-form')[0].reset();
            var invoiceId = $('#pay_invoice_id').val();
            window.orabooksShowInvoiceDetail(invoiceId);
            alert('Payment recorded!');
        }).fail(function() {
            alert('Failed to record payment.');
        });
    });

    // ==================== MODAL HELPERS ====================
    $(document).on('click', '.orabooks-modal-close, .orabooks-modal-cancel, .orabooks-modal-backdrop', function() {
        $(this).closest('.orabooks-modal').fadeOut(200);
    });

    // ==================== REPORTS ====================
    window.orabooksLoadReports = function(data) {
        if (data) {
            window.orabooksRenderReports(data);
            return;
        }
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_customer_stats',
            org_id: 0
        }, function(r) {
            if (r.data) window.orabooksRenderReports(r.data);
        });
    };

    window.orabooksRenderReports = function(stats) {
        $('#orabooks-ar-revenue').text('$' + parseFloat(stats.total_revenue || 0).toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#orabooks-ar-outstanding').text('$' + parseFloat(stats.outstanding_ar || 0).toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#orabooks-ar-paid').text(stats.paid_invoices || 0);
        $('#orabooks-ar-overdue').text(stats.overdue_invoices || 0);
    };

    // =============================================
    // INVOICE DEEP LINK — auto-load invoice from ?invoice_id= query param
    // =============================================
    (function() {
        var urlParams = new URLSearchParams(window.location.search);
        var invoiceId = urlParams.get('invoice_id');

        if (invoiceId && $('.orabooks-customers').length) {
            custCurrentTab = 'invoices';
            $('#orabooks-cust-tabs .nav-tab').removeClass('nav-tab-active');
            $('#orabooks-cust-tabs .nav-tab[data-tab="invoices"]').addClass('nav-tab-active');
            $('.orabooks-tab-content').hide();
            $('#orabooks-tab-invoices').fadeIn(200);

            orabooksLoadInvoices();
            window.orabooksShowInvoiceDetail(parseInt(invoiceId, 10));

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

    // Auto-load tables on page load
    if ($('#orabooks-orgs-table-body').length) {
        orabooksLoadOrgs();
    }
    if ($('#orabooks-users-table-body').length) {
        orabooksLoadUsers();
    }
    if ($('#orabooks-audit-table-body').length) {
        orabooksLoadAuditLogs();
    }
    if ($('#coa-org-select').length) {
        orabooksLoadCoAOrgs();
    }
});