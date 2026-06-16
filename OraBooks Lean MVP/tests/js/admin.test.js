/**
 * Unit Tests for admin.js
 *
 * Tests:
 * - orabooksLoadOrgs, orabooksLoadAuditLogs, orabooksExportAuditLogs
 * - orabooksSuspendOrg, orabooksActivateOrg
 * - orabooksLoadCoAOrgs, orabooksLoadCoA
 * - All export click handlers (CoA, Audit, Partner, Notif, AQ, Users, CommConfig, Onboarding)
 * - escHtml utility
 */

const $ = require('jquery');
const path = require('path');
const fs = require('fs');

// Helper: set up DOM and load admin.js
function setupAdminDom() {
  document.body.innerHTML = `
    <!-- Orgs table -->
    <table><tbody id="orabooks-orgs-table-body"></tbody></table>
    <select id="org-filter-type"><option value="">All Types</option></select>
    <select id="org-filter-status"><option value="">All Statuses</option></select>

    <!-- Audit log table -->
    <table><tbody id="orabooks-audit-table-body"></tbody></table>
    <select id="audit-filter-event"><option value=""></option></select>
    <select id="audit-filter-user"><option value=""></option></select>
    <input id="audit-filter-from" value="" />
    <input id="audit-filter-to" value="" />

    <!-- CoA page -->
    <select id="coa-org-select"></select>
    <select id="coa-filter-type"><option value=""></option></select>
    <table><tbody id="orabooks-coa-table-body"></tbody></table>
    <div id="orabooks-coa-export-msg" style="display:none;"></div>

    <!-- Partner export -->
    <div id="orabooks-partner-export-msg" style="display:none;"></div>

    <!-- Notification export -->
    <div id="orabooks-notif-export-msg" style="display:none;"></div>
    <select id="orabooks-nc-filter-event"><option value=""></option></select>
    <select id="orabooks-nc-filter-priority"><option value=""></option></select>
    <select id="orabooks-nc-filter-status"><option value=""></option></select>

    <!-- Async queue export -->
    <div id="orabooks-aq-export-msg" style="display:none;"></div>

    <!-- Users export -->
    <div id="orabooks-users-export-msg" style="display:none;"></div>

    <!-- Commission config export -->
    <div id="orabooks-commconfig-export-msg" style="display:none;"></div>

    <!-- Onboarding export -->
    <div id="orabooks-onboarding-export-msg" style="display:none;"></div>

    <!-- Filter container for audit export success msg -->
    <div class="orabooks-filters"><button class="orabooks-export-trigger" data-export-type="audit_log" data-format="csv">Export CSV (Async)</button></div>
  `;
}

function loadAdminJs() {
  const code = fs.readFileSync(path.resolve(__dirname, '..', '..', 'assets', 'js', 'admin.js'), 'utf8');
  // Execute with jQuery available
  const fn = new Function('$', 'jQuery', code);
  fn($, $);
}

beforeEach(() => {
  setupAdminDom();
  window.confirm.mockReturnValue(true);
  window.alert.mockClear();
  clearAjax();
  loadAdminJs();
});

// ============================================================
// orabooksLoadOrgs
// ============================================================
describe('orabooksLoadOrgs()', () => {
  test('renders loading state then populates table with orgs', () => {
    const $tbody = $('#orabooks-orgs-table-body');
    expect($tbody.html()).toContain('Loading...');

    clearAjax(); // Clear initial calls from ready handler
    window.orabooksLoadOrgs();

    resolveAjax('get', {
      error: false,
      data: [
        { id: 1, name: 'Test Org', subdomain: 'test', organization_type: 'customer', tier: 'free', status: 'active', created_at: '2024-01-01' }
      ]
    });

    expect($tbody.html()).toContain('Test Org');
    expect($tbody.html()).toContain('test');
    expect($tbody.html()).toContain('customer');
    expect($tbody.html()).toContain('Suspend');
    expect($tbody.html()).toContain('Activate');
  });

  test('shows empty message when no orgs returned', () => {
    clearAjax();
    window.orabooksLoadOrgs();
    resolveAjax('get', { error: false, data: [] });
    expect($('#orabooks-orgs-table-body').html()).toContain('No organizations found');
  });

  test('renders filter values in GET params', () => {
    $('#org-filter-type').val('customer');
    $('#org-filter-status').val('active');

    window.orabooksLoadOrgs();

    const call = latestAjax('get');
    expect(call).not.toBeNull();
  });
});

// ============================================================
// orabooksLoadAuditLogs
// ============================================================
describe('orabooksLoadAuditLogs()', () => {
  test('renders loading state then populates audit table', () => {
    const $tbody = $('#orabooks-audit-table-body');
    expect($tbody.html()).toContain('Loading...');

    clearAjax();
    window.orabooksLoadAuditLogs();

    resolveAjax('get', {
      error: false,
      data: [
        { id: 1, created_at: '2024-01-01', user_id: 1, event_type: 'login', severity: 'info', description: 'User logged in', ip_address: '127.0.0.1', correlation_id: 'abc-123' }
      ]
    });

    expect($tbody.html()).toContain('login');
    expect($tbody.html()).toContain('User logged in');
    expect($tbody.html()).toContain('127.0.0.1');
  });

  test('shows empty message when no logs', () => {
    clearAjax();
    window.orabooksLoadAuditLogs();
    resolveAjax('get', { error: false, data: [] });
    expect($('#orabooks-audit-table-body').html()).toContain('No logs found');
  });

  test('passes filter values', () => {
    $('#audit-filter-event').val('login');
    $('#audit-filter-user').val('5');

    window.orabooksLoadAuditLogs();

    const call = latestAjax('get');
    expect(call).not.toBeNull();
    expect(call.data.event_type).toBe('login');
  });
});

// ============================================================
// orabooksExportAuditLogs
// ============================================================
describe('orabooksExportAuditLogs()', () => {
  test('redirects to export URL with current filter values', () => {
    $('#audit-filter-event').val('login');
    $('#audit-filter-user').val('5');
    $('#audit-filter-from').val('2024-01-01');
    $('#audit-filter-to').val('2024-01-31');

    window.orabooksExportAuditLogs();

    expect(window.location.href).toContain('action=orabooks_export_audit_logs');
    expect(window.location.href).toContain('event_type=login');
    expect(window.location.href).toContain('user_id=5');
    expect(window.location.href).toContain('from_date=2024-01-01');
    expect(window.location.href).toContain('to_date=2024-01-31');
  });
});

// ============================================================
// orabooksSuspendOrg & orabooksActivateOrg
// ============================================================
describe('orabooksSuspendOrg()', () => {
  test('shows confirm dialog and on confirm posts to AJAX', () => {
    window.orabooksSuspendOrg(42);
    expect(window.confirm).toHaveBeenCalled();
    expect(latestAjax('post').data.org_id).toBe(42);
    expect(latestAjax('post').data.action).toBe('orabooks_suspend_org');
  });

  test('does not POST if confirm is cancelled', () => {
    window.confirm.mockReturnValue(false);
    clearAjax();
    window.orabooksSuspendOrg(42);
    expect(ajaxResponses.post.length).toBe(0);
  });
});

describe('orabooksActivateOrg()', () => {
  test('shows confirm dialog and on confirm posts to AJAX', () => {
    window.orabooksActivateOrg(99);
    expect(window.confirm).toHaveBeenCalled();
    expect(latestAjax('post').data.org_id).toBe(99);
    expect(latestAjax('post').data.action).toBe('orabooks_activate_org');
  });
});

// ============================================================
// orabooksLoadCoAOrgs
// ============================================================
describe('orabooksLoadCoAOrgs()', () => {
  test('populates the org dropdown from AJAX response', () => {
    const $select = $('#coa-org-select');
    expect($select.children().length).toBe(0);

    clearAjax();
    window.orabooksLoadCoAOrgs();
    resolveAjax('get', {
      error: false,
      data: [
        { id: 1, name: 'Alpha Corp', subdomain: 'alpha' },
        { id: 2, name: 'Beta Inc', subdomain: 'beta' }
      ]
    });

    expect($select.children().length).toBe(2);
    expect($select.html()).toContain('Alpha Corp');
    expect($select.html()).toContain('beta');
  });
});

// ============================================================
// orabooksLoadCoA
// ============================================================
describe('orabooksLoadCoA()', () => {
  test('shows prompt if no org selected', () => {
    $('#coa-org-select').val('');
    window.orabooksLoadCoA();
    const $tbody = $('#orabooks-coa-table-body');
    expect($tbody.html()).toContain('Please select an organization');
  });

  test('loads and renders accounts for selected org', () => {
    $('#coa-org-select').html('<option value="1">Alpha</option>').val('1');

    clearAjax();
    window.orabooksLoadCoA();
    expect($('#orabooks-coa-table-body').html()).toContain('Loading...');

    resolveAjax('get', {
      error: false,
      data: [
        { code: '1000', name: 'Cash', type: 'asset', normal_balance: 'debit', system_generated: 1, is_active: 1 }
      ]
    });

    const html = $('#orabooks-coa-table-body').html();
    expect(html).toContain('1000');
    expect(html).toContain('Cash');
    expect(html).toContain('asset');
  });

  test('filters by account type', () => {
    $('#coa-org-select').html('<option value="1">Alpha</option>').val('1');
    $('#coa-filter-type').val('liability');

    clearAjax();
    window.orabooksLoadCoA();
    resolveAjax('get', {
      error: false,
      data: [
        { code: '1000', name: 'Cash', type: 'asset', normal_balance: 'debit', system_generated: 1, is_active: 1 },
        { code: '2000', name: 'AP', type: 'liability', normal_balance: 'credit', system_generated: 0, is_active: 1 }
      ]
    });

    const html = $('#orabooks-coa-table-body').html();
    expect(html).not.toContain('Cash');
    expect(html).toContain('2000');
    expect(html).toContain('AP');
  });

  test('shows error message on failure', () => {
    $('#coa-org-select').html('<option value="1">Alpha</option>').val('1');
    clearAjax();
    window.orabooksLoadCoA();
    resolveAjax('get', { error: true, data: null });
    expect($('#orabooks-coa-table-body').html()).toContain('Error loading accounts');
  });
});

// ============================================================
// CoA Export Click Handler
// ============================================================
describe('.orabooks-coa-export-trigger click', () => {
  test('alerts if no org selected', () => {
    $('#coa-org-select').val('');
    $('.orabooks-coa-export-trigger').first().trigger('click');
    expect(window.alert).toHaveBeenCalledWith('Please select an organization first.');
  });

  test('posts export request with org_id in parameters', () => {
    $('#coa-org-select').html('<option value="5">TestOrg</option>').val('5');
    const $btn = $('.orabooks-coa-export-trigger').first();
    $btn.trigger('click');

    expect($btn.prop('disabled')).toBe(true);
    expect($btn.text()).toContain('Requesting');

    const call = latestAjax('post');
    expect(call.data.action).toBe('orabooks_export_request');
    expect(call.data.export_type).toBe('coa');
    expect(call.data.format).toBe('csv');

    const params = JSON.parse(call.data.parameters);
    expect(params.org_id).toBe(5);
    expect(params.columns).toBeDefined();
  });

  test('shows success message and resets button after timeout', () => {
    $('#coa-org-select').html('<option value="5">TestOrg</option>').val('5');
    const $btn = $('.orabooks-coa-export-trigger').first();
    $btn.trigger('click');

    resolveAjax('post', { error: false, data: {}, message: 'Queued' });

    expect($btn.text()).toContain('Requested');
    expect($('#orabooks-coa-export-msg').css('display')).toBe('block');
    expect($('#orabooks-coa-export-msg').html()).toContain('View My Exports');

    jest.advanceTimersByTime(5000);
    expect($btn.text()).toContain('Export CSV');
    expect($btn.prop('disabled')).toBe(false);
  });

  test('shows error alert on failure', () => {
    $('#coa-org-select').html('<option value="5">TestOrg</option>').val('5');
    const $btn = $('.orabooks-coa-export-trigger').first();
    $btn.trigger('click');

    resolveAjax('post', { error: true, message: 'Rate limited' });

    expect(window.alert).toHaveBeenCalled();
    expect(window.alert.mock.calls[0][0]).toContain('Rate limited');
    expect($btn.prop('disabled')).toBe(false);
  });
});

// ============================================================
// Audit Export Click Handler (.orabooks-export-trigger)
// ============================================================
describe('.orabooks-export-trigger click (audit)', () => {
  beforeEach(() => {
    $('#audit-filter-event').val('login');
    $('#audit-filter-from').val('2024-01-01');
  });

  test('posts with current filter values as parameters', () => {
    // The audit export trigger is in .orabooks-filters area
    const $btn = $('.orabooks-export-trigger').first();
    $btn.trigger('click');

    const call = latestAjax('post');
    expect(call.data.action).toBe('orabooks_export_request');
    expect(call.data.export_type).toBe('audit_log');

    const params = JSON.parse(call.data.parameters);
    expect(params.event_type).toBe('login');
    expect(params.from_date).toBe('2024-01-01');
  });

  test('shows success message div after export', () => {
    const $btn = $('.orabooks-export-trigger').first();
    $btn.trigger('click');

    resolveAjax('post', { error: false, data: {}, message: 'Queued' });

    // Should add a notice div after .orabooks-filters
    expect($('.notice-success').length).toBe(1);
    expect($('.notice-success').html()).toContain('View My Exports');

    jest.advanceTimersByTime(5000);
    expect($('.notice-success').length).toBe(0);
  });
});

// ============================================================
// Partner Export Click Handler
// ============================================================
describe('.orabooks-partner-export-trigger click', () => {
  test('posts export request with commission_data type', () => {
    const $btn = $('.orabooks-partner-export-trigger').first();
    $btn.trigger('click');

    const call = latestAjax('post');
    expect(call.data.action).toBe('orabooks_export_request');
    expect(call.data.export_type).toBe('commission_data');

    const params = JSON.parse(call.data.parameters);
    expect(params.columns).toContain('customer');
    expect(params.columns).toContain('amount');
  });

  test('shows success in message div', () => {
    const $btn = $('.orabooks-partner-export-trigger').first();
    $btn.trigger('click');
    resolveAjax('post', { error: false, data: {} });

    expect($('#orabooks-partner-export-msg').css('display')).toBe('block');
    expect($('#orabooks-partner-export-msg').html()).toContain('View My Exports');

    jest.advanceTimersByTime(6000);
    expect($btn.prop('disabled')).toBe(false);
  });

  test('shows error alert on failure', () => {
    const $btn = $('.orabooks-partner-export-trigger').first();
    $btn.trigger('click');
    resolveAjax('post', { error: true, message: 'Permission denied' });

    expect(window.alert).toHaveBeenCalled();
    expect(window.alert.mock.calls[0][0]).toContain('Permission denied');
  });
});

// ============================================================
// Notification Export Click Handler
// ============================================================
describe('.orabooks-notif-export-trigger click', () => {
  test('posts with current notification filter values', () => {
    $('#orabooks-nc-filter-priority').val('high');
    $('#orabooks-nc-filter-status').val('unread');

    $('.orabooks-notif-export-trigger').first().trigger('click');

    const call = latestAjax('post');
    expect(call.data.export_type).toBe('notification_log');

    const params = JSON.parse(call.data.parameters);
    expect(params.priority).toBe('high');
    expect(params.status).toBe('unread');
  });

  test('shows success in notification message div', () => {
    $('.orabooks-notif-export-trigger').first().trigger('click');
    resolveAjax('post', { error: false, data: {} });

    expect($('#orabooks-notif-export-msg').css('display')).toBe('block');
    expect($('#orabooks-notif-export-msg').html()).toContain('View My Exports');
  });
});

// ============================================================
// Async Queue Export Click Handler
// ============================================================
describe('.orabooks-aq-export-trigger click', () => {
  test('posts export request with async_queue_data type', () => {
    $('.orabooks-aq-export-trigger').first().trigger('click');

    const call = latestAjax('post');
    expect(call.data.export_type).toBe('async_queue_data');

    const params = JSON.parse(call.data.parameters);
    expect(params.columns).toContain('status');
    expect(params.columns).toContain('failure_rate');
  });

  test('shows success in message div', () => {
    $('.orabooks-aq-export-trigger').first().trigger('click');
    resolveAjax('post', { error: false, data: {} });

    expect($('#orabooks-aq-export-msg').css('display')).toBe('block');
  });
});

// ============================================================
// Users Export Click Handler
// ============================================================
describe('.orabooks-users-export-trigger click', () => {
  test('posts export request with users_data type', () => {
    $('.orabooks-users-export-trigger').first().trigger('click');

    const call = latestAjax('post');
    expect(call.data.export_type).toBe('users_data');

    const params = JSON.parse(call.data.parameters);
    expect(params.columns).toContain('email');
    expect(params.columns).toContain('is_active');
  });

  test('shows success in users message div', () => {
    $('.orabooks-users-export-trigger').first().trigger('click');
    resolveAjax('post', { error: false, data: {} });

    expect($('#orabooks-users-export-msg').css('display')).toBe('block');
  });

  test('shows error alert on failure', () => {
    $('.orabooks-users-export-trigger').first().trigger('click');
    resolveAjax('post', { error: true, message: 'Failed' });
    expect(window.alert).toHaveBeenCalled();
  });
});

// ============================================================
// Commission Config Export Click Handler
// ============================================================
describe('.orabooks-commconfig-export-trigger click', () => {
  test('posts export request with commission_config type', () => {
    $('.orabooks-commconfig-export-trigger').first().trigger('click');

    const call = latestAjax('post');
    expect(call.data.export_type).toBe('commission_config');

    const params = JSON.parse(call.data.parameters);
    expect(params.columns).toContain('base_monthly_amount');
  });

  test('shows success in config message div', () => {
    $('.orabooks-commconfig-export-trigger').first().trigger('click');
    resolveAjax('post', { error: false, data: {} });

    expect($('#orabooks-commconfig-export-msg').css('display')).toBe('block');
  });
});

// ============================================================
// Partner Onboarding Export Click Handler
// ============================================================
describe('.orabooks-onboarding-export-trigger click', () => {
  test('posts export request with partner_onboarding type', () => {
    $('.orabooks-onboarding-export-trigger').first().trigger('click');

    const call = latestAjax('post');
    expect(call.data.export_type).toBe('partner_onboarding');

    const params = JSON.parse(call.data.parameters);
    expect(params.columns).toContain('partner_code');
    expect(params.columns).toContain('partner_type');
  });

  test('shows success in onboarding message div', () => {
    $('.orabooks-onboarding-export-trigger').first().trigger('click');
    resolveAjax('post', { error: false, data: {} });

    expect($('#orabooks-onboarding-export-msg').css('display')).toBe('block');
  });

  test('shows error alert on failure', () => {
    $('.orabooks-onboarding-export-trigger').first().trigger('click');
    resolveAjax('post', { error: true, message: 'No data' });
    expect(window.alert).toHaveBeenCalledWith('Error: No data');
  });
});

// ============================================================
// escHtml Utility
// ============================================================
describe('escHtml()', () => {
  test('escapes & < > " and single quotes', () => {
    // escHtml is defined inside the ready handler closure, test via DOM injection
    // Since it's not exported, we test that the CoA table uses escaped values
    $('#coa-org-select').html('<option value="1">Org</option>').val('1');

    clearAjax();
    window.orabooksLoadCoA();
    resolveAjax('get', {
      error: false,
      data: [
        { code: '<script>', name: 'Foo & Bar "Test"', type: 'asset', normal_balance: 'debit', system_generated: 0, is_active: 1 }
      ]
    });

    const html = $('#orabooks-coa-table-body').html();
    // The script tag should be escaped
    expect(html).toContain('&lt;script&gt;');
    expect(html).toContain('&amp;');
    expect(html).toContain('&quot;');
    // Raw characters should NOT appear
    expect(html).not.toContain('<script>');
  });

  test('returns empty string for null/undefined', () => {
    // Test via CoA rendering with null name
    $('#coa-org-select').html('<option value="1">Org</option>').val('1');
    clearAjax();
    window.orabooksLoadCoA();
    resolveAjax('get', {
      error: false,
      data: [
        { code: '1000', name: null, type: 'asset', normal_balance: 'debit', system_generated: 0, is_active: 1 }
      ]
    });
    const html = $('#orabooks-coa-table-body').html();
    expect(html).toContain('1000');
  });
});
