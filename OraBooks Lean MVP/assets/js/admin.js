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
    
    // Auto-load tables on page load
    if ($('#orabooks-orgs-table-body').length) {
        orabooksLoadOrgs();
    }
    if ($('#orabooks-audit-table-body').length) {
        orabooksLoadAuditLogs();
    }
});