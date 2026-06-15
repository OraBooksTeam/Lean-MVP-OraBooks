/**
 * OraBooks Admin JavaScript
 */
jQuery(document).ready(function($) {
    
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
            }
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
            }
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
    // FRONTEND EXPORT BUTTON HANDLER (for shortcode pages in admin context)
    // =============================================
    // The frontend.js handles .orabooks-export-trigger for frontend pages.
    // When these shortcodes are rendered in the admin, admin.js handles them.
    // The admin audit page override already handles .orabooks-export-trigger for audit exports.

    // Auto-load tables on page load
    if ($('#orabooks-orgs-table-body').length) {
        orabooksLoadOrgs();
    }
    if ($('#orabooks-audit-table-body').length) {
        orabooksLoadAuditLogs();
    }
    if ($('#coa-org-select').length) {
        orabooksLoadCoAOrgs();
    }
});