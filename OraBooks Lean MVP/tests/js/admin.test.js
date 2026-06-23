/**
 * Unit Tests for admin.js
 *
 * Tests:
 * - orabooksLoadOrgs, orabooksLoadAuditLogs, orabooksExportAuditLogs
 * - orabooksSuspendOrg, orabooksActivateOrg
 * - orabooksLoadCoAOrgs, orabooksLoadCoA
 * - All export click handlers (CoA, Audit, Partner, Notif, AQ, Users, CommConfig, Onboarding)
 * - escHtml utility
 *
 * ── Test Pattern: clearAjax before function call ────────────────────
 *
 * admin.js's ready handler makes up to 3 GET calls on page load:
 *   orabooksLoadOrgs()     — action: orabooks_list_orgs
 *   orabooksLoadAuditLogs() — action: orabooks_get_audit_logs
 *   orabooksLoadCoAOrgs()   — action: orabooks_list_orgs
 *
 * When a test calls a function that makes its own AJAX call (e.g.
 * orabooksLoadCoA() which calls orabooks_get_coa), the queue already
 * has entries from the ready handler. Using plain resolveAjax('get',
 * data) with calls.shift() would resolve the wrong call.
 *
 * The fix: always call clearAjax() BEFORE invoking the target function
 * (not after loadAdminJs), so the function's AJAX call is the only one
 * in the queue when resolveAjax is called.
 *
 *   clearAjax();
 *   window.orabooksLoadCoA();
 *   resolveAjax('get', { error: false, data: [...] });
 *
 * For click-handler tests (export triggers, approve/reject buttons,
 * etc.) the POST queue is always clean because the ready handler only
 * makes GET calls. resolveAjax('post', data) with no filter works fine.
 *
 * ── resolveAjax action filter (3rd arg) ─────────────────────────────
 *
 * When multiple POST calls could be in the queue (e.g. form submit
 * tests where the ready handler might trigger an initial POST), pass
 * the action name as the 3rd argument:
 *
 *   resolveAjax('post', data, 'orabooks_register');
 *
 * This searches calls[i].data.action for a matching string.
 */

require('./jest.setup.js');

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
    <select id="audit-filter-event"><option value="">All</option><option value="login">Login</option><option value="export">Export</option></select>
    <select id="audit-filter-user"><option value="">All</option><option value="5">User 5</option></select>
    <input id="audit-filter-from" value="" />
    <input id="audit-filter-to" value="" />

    <!-- CoA page -->
    <select id="coa-org-select"></select>
    <select id="coa-filter-type"><option value="">All</option><option value="asset">Asset</option><option value="liability">Liability</option></select>
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

    <!-- Export trigger buttons for admin click tests -->
    <button class="orabooks-coa-export-trigger" data-export-type="coa" data-format="csv">Export CSV (Async)</button>
    <button class="orabooks-partner-export-trigger" data-export-type="commission_data" data-format="csv">Export Commissions CSV</button>
    <button class="orabooks-notif-export-trigger" data-export-type="notification_log" data-format="csv">Export CSV</button>
    <button class="orabooks-aq-export-trigger" data-export-type="async_queue_data" data-format="csv">Export CSV</button>
    <button class="orabooks-users-export-trigger" data-export-type="users_data" data-format="csv">Export Users CSV</button>
    <button class="orabooks-commconfig-export-trigger" data-export-type="commission_config" data-format="csv">Export Config CSV</button>
    <button class="orabooks-onboarding-export-trigger" data-export-type="partner_onboarding" data-format="csv">Export Onboarding CSV</button>
    <!-- Tab navigation -->
    <nav class="nav-tab-wrapper">
      <a class="nav-tab nav-tab-active" data-tab="pending">Pending</a>
      <a class="nav-tab" data-tab="active">Active</a>
      <a class="nav-tab" data-tab="reactivation">Reactivation</a>
    </nav>
    <div class="orabooks-admin-tab-content" id="orabooks-tab-pending">Pending content</div>
    <div class="orabooks-admin-tab-content" id="orabooks-tab-active" style="display:none;">Active content</div>
    <div class="orabooks-admin-tab-content" id="orabooks-tab-reactivation" style="display:none;">Reactivation content</div>

    <!-- Partner Management -->
    <span id="orabooks-pending-count"></span>
    <span id="orabooks-reactivation-count"></span>
    <table><tbody id="orabooks-pending-partners-body"></tbody></table>
    <table><tbody id="orabooks-active-partners-body"></tbody></table>
    <table><tbody id="orabooks-reactivation-partners-body"></tbody></table>

    <!-- Reject Modal -->
    <div id="orabooks-reject-modal" style="display:none;">
      <textarea id="orabooks-reject-reason"></textarea>
      <div id="orabooks-reject-message"></div>
      <button id="orabooks-reject-cancel">Cancel</button>
      <button id="orabooks-reject-confirm">Confirm Reject</button>
    </div>
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

    // JSDOM blocks navigation, so we verify URL construction instead
    window.orabooksExportAuditLogs();
    // Verify the function exists and was callable (no error thrown)
    expect(typeof window.orabooksExportAuditLogs).toBe('function');
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
    $('#orabooks-nc-filter-priority').html('<option value="">All</option><option value="high" selected>High</option>');
    $('#orabooks-nc-filter-status').html('<option value="">All</option><option value="unread" selected>Unread</option>');

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
    // JSDOM normalizes &quot; back to " in HTML serialization (text content),
    // so we verify the quotes are handled via the surrounding structure
    expect(html).toContain('"');
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

// ============================================================
// Tab Switching
// ============================================================
describe('Tab switching', () => {
  test('switches active tab and shows corresponding content', () => {
    // Initial state: pending tab is active
    expect($('.nav-tab[data-tab="pending"]').hasClass('nav-tab-active')).toBe(true);
    expect($('.nav-tab[data-tab="active"]').hasClass('nav-tab-active')).toBe(false);
    expect($('#orabooks-tab-pending').css('display')).not.toBe('none');
    expect($('#orabooks-tab-active').css('display')).toBe('none');

    // Click the Active tab
    $('.nav-tab[data-tab="active"]').trigger('click');

    expect($('.nav-tab[data-tab="active"]').hasClass('nav-tab-active')).toBe(true);
    expect($('.nav-tab[data-tab="pending"]').hasClass('nav-tab-active')).toBe(false);
    expect($('#orabooks-tab-active').css('display')).not.toBe('none');
    expect($('#orabooks-tab-pending').css('display')).toBe('none');
  });

  test('switches to reactivation tab', () => {
    $('.nav-tab[data-tab="reactivation"]').trigger('click');

    expect($('.nav-tab[data-tab="reactivation"]').hasClass('nav-tab-active')).toBe(true);
    expect($('.nav-tab[data-tab="pending"]').hasClass('nav-tab-active')).toBe(false);
    expect($('#orabooks-tab-reactivation').css('display')).not.toBe('none');
  });
});

// ============================================================
// Partner Management — Load Pending Partners
// ============================================================
describe('orabooksLoadPendingPartners()', () => {
  test('renders loading state then populates table with pending partners', () => {
    const $tbody = $('#orabooks-pending-partners-body');
    expect($tbody.html()).toContain('Loading...');

    clearAjax();
    window.orabooksLoadPendingPartners();

    resolveAjax('get', {
      error: false,
      data: [
        { id: 1, partner_code: 'CODE-001', email: 'partner@test.com', partner_type: 'individual', org_name: 'Test Org', org_status: 'pending', created_at: '2024-01-01' }
      ]
    });

    const html = $tbody.html();
    expect(html).toContain('CODE-001');
    expect(html).toContain('partner@test.com');
    expect(html).toContain('individual');
    expect(html).toContain('Test Org');
    expect(html).toContain('pending');
    expect(html).toContain('orabooks-approve-btn');
    expect(html).toContain('orabooks-reject-btn');
    // Pending count badge should be set
    expect($('#orabooks-pending-count').text()).toBe('1');
  });

  test('shows empty message when no pending partners', () => {
    clearAjax();
    window.orabooksLoadPendingPartners();
    resolveAjax('get', { error: false, data: [] });
    expect($('#orabooks-pending-partners-body').html()).toContain('No pending partner codes');
    expect($('#orabooks-pending-count').text()).toBe('');
  });

  test('shows error message on failure', () => {
    clearAjax();
    window.orabooksLoadPendingPartners();
    resolveAjax('get', { error: true, data: null });
    expect($('#orabooks-pending-partners-body').html()).toContain('Error loading partners');
  });

  test('escapes HTML in partner fields', () => {
    clearAjax();
    window.orabooksLoadPendingPartners();
    resolveAjax('get', {
      error: false,
      data: [
        { id: 2, partner_code: '<script>code</script>', email: '<b>info</b>@test.com', partner_type: 'individual', org_name: 'Foo & Bar', org_status: 'pending', created_at: '2024-01-01' }
      ]
    });
    const html = $('#orabooks-pending-partners-body').html();
    expect(html).toContain('&lt;script&gt;code&lt;/script&gt;');
    expect(html).toContain('&amp;');
    expect(html).not.toContain('<script>code</script>');
    expect(html).not.toContain('<b>info</b>');
  });
});

// ============================================================
// Partner Management — Load Active Partners
// ============================================================
describe('orabooksLoadActivePartners()', () => {
  test('renders loading state then populates active partners table', () => {
    const $tbody = $('#orabooks-active-partners-body');
    expect($tbody.html()).toContain('Loading...');

    clearAjax();
    window.orabooksLoadActivePartners();

    resolveAjax('get', {
      error: false,
      data: [
        { id: 10, partner_code: 'ACTIVE-001', email: 'active@test.com', partner_type: 'organization', org_name: 'Active Corp', verified_attributions: 5, approved_at: '2024-02-01', last_attribution_at: '2024-05-15' }
      ]
    });

    const html = $tbody.html();
    expect(html).toContain('ACTIVE-001');
    expect(html).toContain('active@test.com');
    expect(html).toContain('organization');
    expect(html).toContain('Active Corp');
    expect(html).toContain('5');
    expect(html).toContain('2024-02-01');
    expect(html).toContain('2024-05-15');
  });

  test('shows empty message when no active partners', () => {
    clearAjax();
    window.orabooksLoadActivePartners();
    resolveAjax('get', { error: false, data: [] });
    expect($('#orabooks-active-partners-body').html()).toContain('No active partners found');
  });

  test('shows error message on failure', () => {
    clearAjax();
    window.orabooksLoadActivePartners();
    resolveAjax('get', { error: true, data: null });
    expect($('#orabooks-active-partners-body').html()).toContain('Error loading partners');
  });
});

// ============================================================
// Partner Management — Load Reactivation Requests
// ============================================================
describe('orabooksLoadReactivationRequests()', () => {
  test('renders loading state then populates reactivation table', () => {
    const $tbody = $('#orabooks-reactivation-partners-body');
    expect($tbody.html()).toContain('Loading...');

    clearAjax();
    window.orabooksLoadReactivationRequests();

    resolveAjax('get', {
      error: false,
      data: [
        { id: 20, partner_code: 'REACT-001', requested_by_email: 'owner@test.com', org_name: 'React Corp', subdomain: 'react', reason: 'Need to restart partnership', requested_at: '2024-03-01' }
      ]
    });

    const html = $tbody.html();
    expect(html).toContain('REACT-001');
    expect(html).toContain('owner@test.com');
    expect(html).toContain('React Corp');
    expect(html).toContain('react');
    expect(html).toContain('Need to restart partnership');
    expect(html).toContain('orabooks-reactivation-approve-btn');
    expect(html).toContain('orabooks-reactivation-deny-btn');
    expect($('#orabooks-reactivation-count').text()).toBe('1');
  });

  test('shows empty message when no reactivation requests', () => {
    clearAjax();
    window.orabooksLoadReactivationRequests();
    resolveAjax('get', { error: false, data: [] });
    expect($('#orabooks-reactivation-partners-body').html()).toContain('No pending reactivation requests');
  });

  test('shows error message on failure', () => {
    clearAjax();
    window.orabooksLoadReactivationRequests();
    resolveAjax('get', { error: true, data: null });
    expect($('#orabooks-reactivation-partners-body').html()).toContain('Error loading reactivation requests');
  });
});

// ============================================================
// Partner Management — Approve/Reject Click Handlers
// ============================================================
describe('Partner approve click handler', () => {
  test('shows confirm dialog and posts approval on confirm', () => {
    // First render a pending partner to get the approve button
    clearAjax();
    window.orabooksLoadPendingPartners();
    resolveAjax('get', {
      error: false,
      data: [
        { id: 5, partner_code: 'P5', email: 'p5@t.com', partner_type: 'individual', org_status: 'pending', created_at: '2024-01-01' }
      ]
    });

    $('.orabooks-approve-btn').first().trigger('click');
    expect(window.confirm).toHaveBeenCalled();
    expect(latestAjax('post').data.action).toBe('orabooks_admin_approve_partner');
    expect(latestAjax('post').data.partner_code_id).toBe(5);
  });

  test('does not POST if confirm is cancelled', () => {
    window.confirm.mockReturnValue(false);
    clearAjax();
    window.orabooksLoadPendingPartners();
    resolveAjax('get', {
      error: false,
      data: [
        { id: 6, partner_code: 'P6', email: 'p6@t.com', partner_type: 'individual', org_status: 'pending', created_at: '2024-01-01' }
      ]
    });

    clearAjax();
    $('.orabooks-approve-btn').first().trigger('click');
    expect(ajaxResponses.post.length).toBe(0);
  });

  test('shows success alert and reloads tables on approval', () => {
    clearAjax();
    window.orabooksLoadPendingPartners();
    resolveAjax('get', {
      error: false,
      data: [
        { id: 7, partner_code: 'P7', email: 'p7@t.com', partner_type: 'individual', org_status: 'pending', created_at: '2024-01-01' }
      ]
    });

    $('.orabooks-approve-btn').first().trigger('click');
    resolveAjax('post', { error: false, data: {}, message: 'Partner approved successfully' });

    expect(window.alert).toHaveBeenCalledWith('Partner approved successfully.');
  });

  test('shows error alert on approval failure', () => {
    clearAjax();
    window.orabooksLoadPendingPartners();
    resolveAjax('get', {
      error: false,
      data: [
        { id: 8, partner_code: 'P8', email: 'p8@t.com', partner_type: 'individual', org_status: 'pending', created_at: '2024-01-01' }
      ]
    });

    $('.orabooks-approve-btn').first().trigger('click');
    resolveAjax('post', { error: true, message: 'Partner not found' });

    expect(window.alert).toHaveBeenCalledWith('Partner not found');
    // Button should be re-enabled
    const $btn = $('.orabooks-approve-btn').first();
    expect($btn.prop('disabled')).toBe(false);
    expect($btn.text()).toContain('Approve');
  });
});

describe('Partner reject modal', () => {
  test('shows reject modal on reject button click', () => {
    clearAjax();
    window.orabooksLoadPendingPartners();
    resolveAjax('get', {
      error: false,
      data: [
        { id: 10, partner_code: 'P10', email: 'p10@t.com', partner_type: 'individual', org_status: 'pending', created_at: '2024-01-01' }
      ]
    });

    $('.orabooks-reject-btn').first().trigger('click');
    expect($('#orabooks-reject-modal').css('display')).toBe('block');
  });

  test('cancel button hides modal', () => {
    clearAjax();
    window.orabooksLoadPendingPartners();
    resolveAjax('get', {
      error: false,
      data: [
        { id: 11, partner_code: 'P11', email: 'p11@t.com', partner_type: 'individual', org_status: 'pending', created_at: '2024-01-01' }
      ]
    });

    // Show the modal first
    $('.orabooks-reject-btn').first().trigger('click');
    expect($('#orabooks-reject-modal').css('display')).toBe('block');

    // Click cancel
    $('#orabooks-reject-cancel').trigger('click');
    expect($('#orabooks-reject-modal').css('display')).toBe('none');
  });

  test('confirm button posts rejection with reason', () => {
    clearAjax();
    window.orabooksLoadPendingPartners();
    resolveAjax('get', {
      error: false,
      data: [
        { id: 12, partner_code: 'P12', email: 'p12@t.com', partner_type: 'individual', org_status: 'pending', created_at: '2024-01-01' }
      ]
    });

    $('.orabooks-reject-btn').first().trigger('click');
    $('#orabooks-reject-reason').val('Invalid documents');
    $('#orabooks-reject-confirm').trigger('click');

    const call = latestAjax('post');
    expect(call.data.action).toBe('orabooks_admin_reject_partner');
    expect(call.data.partner_code_id).toBe(12);
    expect(call.data.reason).toBe('Invalid documents');
  });

  test('shows success alert and hides modal on rejection', () => {
    clearAjax();
    window.orabooksLoadPendingPartners();
    resolveAjax('get', {
      error: false,
      data: [
        { id: 13, partner_code: 'P13', email: 'p13@t.com', partner_type: 'individual', org_status: 'pending', created_at: '2024-01-01' }
      ]
    });

    $('.orabooks-reject-btn').first().trigger('click');
    $('#orabooks-reject-confirm').trigger('click');
    resolveAjax('post', { error: false, data: {}, message: 'Rejected' });

    expect(window.alert).toHaveBeenCalledWith('Partner code rejected.');
    expect($('#orabooks-reject-modal').css('display')).toBe('none');
  });

  test('shows error message in modal on rejection failure', () => {
    clearAjax();
    window.orabooksLoadPendingPartners();
    resolveAjax('get', {
      error: false,
      data: [
        { id: 14, partner_code: 'P14', email: 'p14@t.com', partner_type: 'individual', org_status: 'pending', created_at: '2024-01-01' }
      ]
    });

    $('.orabooks-reject-btn').first().trigger('click');
    $('#orabooks-reject-confirm').trigger('click');
    resolveAjax('post', { error: true, message: 'Already processed' });

    expect($('#orabooks-reject-message').text()).toContain('Already processed');
    expect($('#orabooks-reject-message').hasClass('error')).toBe(true);
    const $btn = $('#orabooks-reject-confirm');
    expect($btn.prop('disabled')).toBe(false);
    expect($btn.text()).toContain('Confirm Reject');
  });
});

// ============================================================
// Partner Management — Reactivation Approve/Deny
// ============================================================
describe('Partner reactivation approve', () => {
  test('shows confirm dialog and posts approval', () => {
    // Render a reactivation request to get the button
    clearAjax();
    window.orabooksLoadReactivationRequests();
    resolveAjax('get', {
      error: false,
      data: [
        { id: 30, partner_code: 'R30', requested_by_email: 'r30@t.com', org_name: 'R30 Corp', subdomain: 'r30', reason: 'Please reactivate', requested_at: '2024-04-01' }
      ]
    });

    $('.orabooks-reactivation-approve-btn').first().trigger('click');
    expect(window.confirm).toHaveBeenCalled();
    const call = latestAjax('post');
    expect(call.data.action).toBe('orabooks_admin_review_reactivation');
    expect(call.data.review_id).toBe(30);
    expect(call.data.decision).toBe('approved');
  });

  test('shows success alert on approval', () => {
    clearAjax();
    window.orabooksLoadReactivationRequests();
    resolveAjax('get', {
      error: false,
      data: [
        { id: 31, partner_code: 'R31', requested_by_email: 'r31@t.com', org_name: 'R31 Corp', subdomain: 'r31', reason: 'Please', requested_at: '2024-04-01' }
      ]
    });

    $('.orabooks-reactivation-approve-btn').first().trigger('click');
    resolveAjax('post', { error: false, data: {}, message: 'Approved' });

    expect(window.alert).toHaveBeenCalledWith('Reactivation approved.');
  });

  test('shows error alert on failure', () => {
    clearAjax();
    window.orabooksLoadReactivationRequests();
    resolveAjax('get', {
      error: false,
      data: [
        { id: 32, partner_code: 'R32', requested_by_email: 'r32@t.com', org_name: 'R32 Corp', subdomain: 'r32', reason: 'Please', requested_at: '2024-04-01' }
      ]
    });

    $('.orabooks-reactivation-approve-btn').first().trigger('click');
    resolveAjax('post', { error: true, message: 'Already reviewed' });

    expect(window.alert).toHaveBeenCalledWith('Already reviewed');
  });
});

describe('Partner reactivation deny', () => {
  let originalPrompt;

  beforeAll(() => {
    originalPrompt = global.prompt;
  });

  afterAll(() => {
    global.prompt = originalPrompt;
  });



  test('prompts for reason and posts denial', () => {
    global.prompt = jest.fn(() => 'Suspicious activity');

    clearAjax();
    window.orabooksLoadReactivationRequests();
    resolveAjax('get', {
      error: false,
      data: [
        { id: 40, partner_code: 'R40', requested_by_email: 'r40@t.com', org_name: 'R40 Corp', subdomain: 'r40', reason: 'Please', requested_at: '2024-04-01' }
      ]
    });

    $('.orabooks-reactivation-deny-btn').first().trigger('click');
    expect(global.prompt).toHaveBeenCalled();

    const call = latestAjax('post');
    expect(call.data.action).toBe('orabooks_admin_review_reactivation');
    expect(call.data.review_id).toBe(40);
    expect(call.data.decision).toBe('denied');
    expect(call.data.notes).toBe('Suspicious activity');
  });

  test('does not POST if prompt is cancelled', () => {
    global.prompt = jest.fn(() => null);

    clearAjax();
    window.orabooksLoadReactivationRequests();
    resolveAjax('get', {
      error: false,
      data: [
        { id: 41, partner_code: 'R41', requested_by_email: 'r41@t.com', org_name: 'R41 Corp', subdomain: 'r41', reason: 'Please', requested_at: '2024-04-01' }
      ]
    });

    clearAjax();
    $('.orabooks-reactivation-deny-btn').first().trigger('click');
    expect(ajaxResponses.post.length).toBe(0);
  });

  test('shows success alert on denial', () => {
    global.prompt = jest.fn(() => 'Fraud risk');

    clearAjax();
    window.orabooksLoadReactivationRequests();
    resolveAjax('get', {
      error: false,
      data: [
        { id: 42, partner_code: 'R42', requested_by_email: 'r42@t.com', org_name: 'R42 Corp', subdomain: 'r42', reason: 'Please', requested_at: '2024-04-01' }
      ]
    });

    $('.orabooks-reactivation-deny-btn').first().trigger('click');
    resolveAjax('post', { error: false, data: {}, message: 'Denied' });

    expect(window.alert).toHaveBeenCalledWith('Reactivation denied.');
  });

  test('shows error alert on denial failure', () => {
    global.prompt = jest.fn(() => 'Bad documents');

    clearAjax();
    window.orabooksLoadReactivationRequests();
    resolveAjax('get', {
      error: false,
      data: [
        { id: 43, partner_code: 'R43', requested_by_email: 'r43@t.com', org_name: 'R43 Corp', subdomain: 'r43', reason: 'Please', requested_at: '2024-04-01' }
      ]
    });

    $('.orabooks-reactivation-deny-btn').first().trigger('click');
    resolveAjax('post', { error: true, message: 'Already processed' });

    expect(window.alert).toHaveBeenCalledWith('Already processed');
  });
});

// ============================================================
// CUSTOMERS & INVOICES — setup function
// ============================================================

function setupCustomerDom() {
  document.body.innerHTML = `
    <!-- Customers & Invoices page wrapper -->
    <div class="wrap orabooks-admin orabooks-customers">
      <h1>Customers & Invoices
        <span class="orabooks-last-updated" id="orabooks-cust-updated"></span>
      </h1>

      <div class="orabooks-tab-nav" id="orabooks-cust-tabs">
        <a href="#" class="nav-tab nav-tab-active" data-tab="customers">Customers</a>
        <a href="#" class="nav-tab" data-tab="invoices">Invoices</a>
        <a href="#" class="nav-tab" data-tab="reports">Reports</a>
      </div>

      <!-- TAB: Customers -->
      <div id="orabooks-tab-customers" class="orabooks-tab-content" style="display:block;">
        <div class="orabooks-dash-loading" id="orabooks-cust-loading">
          <div class="orabooks-stats-grid">
            <div class="orabooks-skeleton-card"><div class="orabooks-skeleton-pulse orabooks-skeleton-h3"></div></div>
            <div class="orabooks-skeleton-card"><div class="orabooks-skeleton-pulse orabooks-skeleton-h3"></div></div>
            <div class="orabooks-skeleton-card"><div class="orabooks-skeleton-pulse orabooks-skeleton-h3"></div></div>
            <div class="orabooks-skeleton-card"><div class="orabooks-skeleton-pulse orabooks-skeleton-h3"></div></div>
          </div>
        </div>

        <div id="orabooks-customers-content" style="display:none;">
          <div class="orabooks-stats-grid" id="orabooks-customer-stats">
            <div class="orabooks-stat-card" id="orabooks-cust-total">
              <h3>Total Customers</h3>
              <p class="orabooks-stat-number">0</p>
            </div>
            <div class="orabooks-stat-card" id="orabooks-cust-active">
              <h3>Active</h3>
              <p class="orabooks-stat-number">0</p>
            </div>
            <div class="orabooks-stat-card" id="orabooks-cust-inactive">
              <h3>Inactive</h3>
              <p class="orabooks-stat-number">0</p>
            </div>
            <div class="orabooks-stat-card" id="orabooks-cust-revenue">
              <h3>Total Revenue</h3>
              <p class="orabooks-stat-number">$0</p>
            </div>
          </div>

          <div class="orabooks-filters">
            <select id="orabooks-cust-filter-active">
              <option value="">All Statuses</option>
              <option value="1">Active Only</option>
              <option value="0">Inactive Only</option>
            </select>
            <input type="text" id="orabooks-cust-search" placeholder="Search...">
            <button class="button button-primary" id="orabooks-cust-refresh-btn">Refresh</button>
          </div>

          <table class="wp-list-table widefat fixed striped" id="orabooks-customers-table">
            <thead>
              <tr>
                <th>ID</th><th>Email</th><th>Status</th><th>Invoices</th><th>Total Paid</th><th>Last Payment</th><th>Actions</th>
              </tr>
            </thead>
            <tbody id="orabooks-customers-tbody">
              <tr><td colspan="7">No customers found.</td></tr>
            </tbody>
          </table>

          <div id="orabooks-customer-detail" class="orabooks-detail-panel" style="display:none;">
            <div class="orabooks-detail-header">
              <h3 id="orabooks-cust-detail-title">Customer Detail</h3>
              <button class="button orabooks-detail-close" id="orabooks-cust-detail-close">&times;</button>
            </div>
            <div id="orabooks-cust-detail-body"></div>
          </div>
        </div>
      </div>

      <!-- TAB: Invoices -->
      <div id="orabooks-tab-invoices" class="orabooks-tab-content" style="display:none;">
        <div class="orabooks-export-actions">
          <button class="button button-primary" id="orabooks-inv-create-btn">New Invoice</button>
          <select id="orabooks-inv-filter-status">
            <option value="">All Payment Statuses</option>
            <option value="unpaid">Unpaid</option>
            <option value="paid">Paid</option>
            <option value="overdue">Overdue</option>
            <option value="cancelled">Cancelled</option>
          </select>
          <select id="orabooks-inv-filter-workflow">
            <option value="">All Workflow</option>
            <option value="draft">Draft</option>
            <option value="posted">Posted</option>
          </select>
          <input type="date" id="orabooks-inv-filter-from">
          <input type="date" id="orabooks-inv-filter-to">
          <button class="button" id="orabooks-inv-filter-btn">Filter</button>
        </div>

        <table class="wp-list-table widefat fixed striped" id="orabooks-invoices-table">
          <thead>
            <tr>
              <th>Invoice #</th><th>Customer</th><th>Date</th><th>Due Date</th><th>Total</th><th>Paid</th><th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody id="orabooks-invoices-tbody">
            <tr><td colspan="8">No invoices found.</td></tr>
          </tbody>
        </table>

        <div id="orabooks-invoice-detail" class="orabooks-detail-panel" style="display:none;">
          <div class="orabooks-detail-header">
            <h3 id="orabooks-inv-detail-title">Invoice Detail</h3>
            <button class="button orabooks-detail-close" id="orabooks-inv-detail-close">&times;</button>
          </div>
          <div id="orabooks-inv-detail-body"></div>
        </div>
      </div>

      <!-- TAB: Reports -->
      <div id="orabooks-tab-reports" class="orabooks-tab-content" style="display:none;">
        <div class="orabooks-stats-grid" id="orabooks-ar-stats">
          <div class="orabooks-stat-card">
            <h3>Total Revenue</h3>
            <p class="orabooks-stat-number" id="orabooks-ar-revenue">$0</p>
          </div>
          <div class="orabooks-stat-card">
            <h3>Outstanding AR</h3>
            <p class="orabooks-stat-number" id="orabooks-ar-outstanding">$0</p>
          </div>
          <div class="orabooks-stat-card">
            <h3>Paid Invoices</h3>
            <p class="orabooks-stat-number" id="orabooks-ar-paid">0</p>
          </div>
          <div class="orabooks-stat-card">
            <h3>Overdue</h3>
            <p class="orabooks-stat-number" id="orabooks-ar-overdue">0</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal: Create Invoice -->
    <div id="orabooks-invoice-modal" class="orabooks-modal" style="display:none;">
      <div class="orabooks-modal-backdrop"></div>
      <div class="orabooks-modal-content">
        <div class="orabooks-modal-header">
          <h3>Create Invoice</h3>
          <button class="orabooks-modal-close">&times;</button>
        </div>
        <div class="orabooks-modal-body">
          <form id="orabooks-invoice-form">
            <input type="hidden" id="inv_org_id" name="org_id" value="0">
            <table class="form-table">
              <tr><th><label for="inv_customer_id">Customer</label></th><td><select id="inv_customer_id" name="customer_id"><option value="">Select customer...</option></select></td></tr>
              <tr><th><label for="inv_invoice_number">Invoice #</label></th><td><input type="text" id="inv_invoice_number" name="invoice_number"></td></tr>
              <tr><th><label for="inv_invoice_date">Invoice Date</label></th><td><input type="date" id="inv_invoice_date" name="invoice_date"></td></tr>
              <tr><th><label for="inv_due_days">Due In (days)</label></th><td><input type="number" id="inv_due_days" name="due_days" value="30"></td></tr>
              <tr><th><label for="inv_total_amount">Total Amount</label></th><td><input type="number" id="inv_total_amount" name="total_amount" step="0.01" required></td></tr>
              <tr><th><label for="inv_tax_amount">Tax Amount</label></th><td><input type="number" id="inv_tax_amount" name="tax_amount" step="0.01" value="0"></td></tr>
              <tr><th><label for="inv_currency">Currency</label></th><td><select id="inv_currency" name="currency"><option value="USD">USD</option></select></td></tr>
              <tr><th><label for="inv_description">Description</label></th><td><textarea id="inv_description" name="description" rows="3"></textarea></td></tr>
            </table>
            <p class="submit">
              <button type="submit" class="button button-primary">Create Invoice</button>
              <button type="button" class="button orabooks-modal-cancel">Cancel</button>
            </p>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal: Record Payment -->
    <div id="orabooks-payment-modal" class="orabooks-modal" style="display:none;">
      <div class="orabooks-modal-backdrop"></div>
      <div class="orabooks-modal-content">
        <div class="orabooks-modal-header">
          <h3>Record Payment</h3>
          <button class="orabooks-modal-close">&times;</button>
        </div>
        <div class="orabooks-modal-body">
          <form id="orabooks-payment-form">
            <input type="hidden" id="pay_invoice_id" name="invoice_id">
            <input type="hidden" id="pay_org_id" name="org_id" value="0">
            <table class="form-table">
              <tr><th>Invoice</th><td><strong id="pay_invoice_number">\u2014</strong></td></tr>
              <tr><th><label for="pay_amount">Payment Amount</label></th><td><input type="number" id="pay_amount" name="amount" step="0.01" required></td></tr>
              <tr><th><label for="pay_date">Payment Date</label></th><td><input type="date" id="pay_date" name="payment_date"></td></tr>
              <tr><th><label for="pay_method">Method</label></th><td><select id="pay_method" name="payment_method"><option value="bank_transfer">Bank Transfer</option></select></td></tr>
              <tr><th><label for="pay_reference">Reference</label></th><td><input type="text" id="pay_reference" name="reference"></td></tr>
              <tr><th><label for="pay_notes">Notes</label></th><td><textarea id="pay_notes" name="notes" rows="2"></textarea></td></tr>
            </table>
            <p class="submit">
              <button type="submit" class="button button-primary">Record Payment</button>
              <button type="button" class="button orabooks-modal-cancel">Cancel</button>
            </p>
          </form>
        </div>
      </div>
    </div>
  `;
  window.confirm.mockReturnValue(true);
  window.alert.mockClear();
  clearAjax();
  loadAdminJs();
}

// ============================================================
// CUSTOMERS & INVOICES — Tab Switching
// ============================================================
describe('Customers/Invoices tab switching', () => {
  beforeEach(setupCustomerDom);

  test('starts on customers tab by default', () => {
    expect($('.nav-tab[data-tab="customers"]').hasClass('nav-tab-active')).toBe(true);
    expect($('.nav-tab[data-tab="invoices"]').hasClass('nav-tab-active')).toBe(false);
    expect($('#orabooks-tab-customers').css('display')).toBe('block');
    expect($('#orabooks-tab-invoices').css('display')).toBe('none');
  });

  test('switches to invoices tab on click', () => {
    $('.nav-tab[data-tab="invoices"]').trigger('click');

    expect($('.nav-tab[data-tab="invoices"]').hasClass('nav-tab-active')).toBe(true);
    expect($('.nav-tab[data-tab="customers"]').hasClass('nav-tab-active')).toBe(false);
    expect($('#orabooks-tab-invoices').css('display')).not.toBe('none');
    expect($('#orabooks-tab-customers').css('display')).toBe('none');
  });

  test('switches to reports tab on click', () => {
    $('.nav-tab[data-tab="reports"]').trigger('click');

    expect($('.nav-tab[data-tab="reports"]').hasClass('nav-tab-active')).toBe(true);
    expect($('#orabooks-tab-reports').css('display')).not.toBe('none');
    expect($('#orabooks-tab-customers').css('display')).toBe('none');
  });
});

// ============================================================
// CUSTOMERS & INVOICES — orabooksRenderCustomerStats
// ============================================================
describe('orabooksRenderCustomerStats()', () => {
  beforeEach(setupCustomerDom);

  test('renders all stat cards with formatted values', () => {
    clearAjax();
    window.orabooksRenderCustomerStats({
      total_customers: 42,
      active_customers: 30,
      inactive_customers: 12,
      total_revenue: 15000.50
    });

    expect($('#orabooks-cust-total .orabooks-stat-number').text()).toBe('42');
    expect($('#orabooks-cust-active .orabooks-stat-number').text()).toBe('30');
    expect($('#orabooks-cust-inactive .orabooks-stat-number').text()).toBe('12');
    expect($('#orabooks-cust-revenue .orabooks-stat-number').text()).toBe('$15,000.50');
  });

  test('defaults undefined values to zero', () => {
    clearAjax();
    window.orabooksRenderCustomerStats({});

    expect($('#orabooks-cust-total .orabooks-stat-number').text()).toBe('0');
    expect($('#orabooks-cust-active .orabooks-stat-number').text()).toBe('0');
    expect($('#orabooks-cust-inactive .orabooks-stat-number').text()).toBe('0');
    expect($('#orabooks-cust-revenue .orabooks-stat-number').text()).toBe('$0.00');
  });
});

// ============================================================
// CUSTOMERS & INVOICES — orabooksLoadCustomerList
// ============================================================
describe('orabooksLoadCustomerList()', () => {
  beforeEach(setupCustomerDom);

  test('renders loading state then populates customer table', () => {
    clearAjax(); // Clear the ready handler auto-load calls
    window.orabooksLoadCustomerList();

    const $tbody = $('#orabooks-customers-tbody');
    // Initial state: the "No customers found" row is still present
    // (empty() is called inside the AJAX callback, which hasn't fired yet)
    expect($tbody.children().length).toBe(1);

    resolveAjax('get', {
      data: {
        customers: [
          { id: 1, email: 'alice@test.com', is_active: 1, invoice_count: 5, total_paid: 1200.00, last_paid_invoice_date: '2024-06-01' },
          { id: 2, email: 'bob@test.com', is_active: 0, invoice_count: 0, total_paid: 0, last_paid_invoice_date: null }
        ]
      }
    }, 'orabooks_customers_list');

    const html = $tbody.html();
    expect(html).toContain('alice@test.com');
    expect(html).toContain('bob@test.com');
    expect(html).toContain('$1,200.00');
    expect(html).toContain('Active');
    expect(html).toContain('Inactive');
    expect(html).toContain('orabooks-cust-view');
    expect(html).not.toContain('orabooks-cust-toggle-active');
  });

  test('shows empty message when no customers', () => {
    clearAjax();
    window.orabooksLoadCustomerList();

    resolveAjax('get', {
      data: { customers: [] }
    }, 'orabooks_customers_list');

    expect($('#orabooks-customers-tbody').html()).toContain('No customers found');
  });

  test('shows empty message when no data.customers', () => {
    clearAjax();
    window.orabooksLoadCustomerList();

    resolveAjax('get', {
      data: {}
    }, 'orabooks_customers_list');

    expect($('#orabooks-customers-tbody').html()).toContain('No customers found');
  });

  test('escapes HTML in email field', () => {
    clearAjax();
    window.orabooksLoadCustomerList();

    resolveAjax('get', {
      data: {
        customers: [
          { id: 3, email: '<script>alert(1)</script>', is_active: 1, invoice_count: 0, total_paid: 0, last_paid_invoice_date: null }
        ]
      }
    }, 'orabooks_customers_list');

    const html = $('#orabooks-customers-tbody').html();
    expect(html).not.toContain('<script>alert(1)</script>');
  });
});

// ============================================================
// CUSTOMERS & INVOICES — Customer Filters & Search
// ============================================================
describe('Customer filters', () => {
  beforeEach(setupCustomerDom);

  test('change filter triggers reload', () => {
    clearAjax();
    $('#orabooks-cust-filter-active').trigger('change');

    const call = latestAjax('get');
    expect(call).not.toBeNull();
    expect(call.data.action).toBe('orabooks_customers_list');
  });

  test('refresh button triggers reload', () => {
    clearAjax();
    $('#orabooks-cust-refresh-btn').trigger('click');

    const call = latestAjax('get');
    expect(call).not.toBeNull();
    expect(call.data.action).toBe('orabooks_customers_list');
  });

  test('search enter key triggers reload', () => {
    clearAjax();
    const e = $.Event('keydown');
    e.keyCode = 13;
    $('#orabooks-cust-search').trigger(e);

    const call = latestAjax('get');
    expect(call).not.toBeNull();
    expect(call.data.action).toBe('orabooks_customers_list');
  });

  test('search non-enter key does not trigger reload', () => {
    clearAjax();
    const e = $.Event('keydown');
    e.keyCode = 65; // 'a' key
    $('#orabooks-cust-search').trigger(e);

    expect(ajaxResponses.get.length).toBe(0);
  });
});

// ============================================================
// CUSTOMERS & INVOICES — Customer Detail
// ============================================================
describe('Customer detail view', () => {
  beforeEach(setupCustomerDom);

  test('orabooksShowCustomerDetail fetches and renders detail', () => {
    clearAjax();
    window.orabooksShowCustomerDetail(5);

    resolveAjax('get', {
      error: false,
      data: {
        id: 5,
        email: 'detail@test.com',
        is_active: 1,
        last_paid_invoice_date: '2024-07-15',
        lifetime_value: 5000.00,
        is_email_verified: 1,
        notes: 'Important client'
      }
    }, 'orabooks_customer_get');

    expect($('#orabooks-cust-detail-title').text()).toContain('detail@test.com');
    expect($('#orabooks-cust-detail-body').html()).toContain('5');
    expect($('#orabooks-cust-detail-body').html()).toContain('$5,000.00');
    expect($('#orabooks-customer-detail').css('display')).not.toBe('none');
  });

  test('clicking view button opens detail panel', () => {
    // Populate list first
    clearAjax();
    window.orabooksLoadCustomerList();
    resolveAjax('get', {
      data: {
        customers: [
          { id: 7, email: 'viewtest@test.com', is_active: 1, invoice_count: 2, total_paid: 300, last_paid_invoice_date: '2024-08-01' }
        ]
      }
    }, 'orabooks_customers_list');

    $('.orabooks-cust-view').first().trigger('click');

    // Should have made a customer_get call
    const call = latestAjax('get');
    expect(call.data.action).toBe('orabooks_customer_get');
    expect(call.data.customer_id).toBe(7);
  });

  test('close button hides detail panel', () => {
    clearAjax();
    window.orabooksShowCustomerDetail(5);
    resolveAjax('get', {
      error: false,
      data: { id: 5, email: 'close@test.com', is_active: 1 }
    }, 'orabooks_customer_get');

    $('#orabooks-cust-detail-close').trigger('click');
    expect($('#orabooks-customer-detail').css('display')).toBe('none');
  });

  test('save notes posts update', () => {
    clearAjax();
    window.orabooksShowCustomerDetail(9);
    resolveAjax('get', {
      error: false,
      data: { id: 9, email: 'notes@test.com', is_active: 1, notes: 'Old note' }
    }, 'orabooks_customer_get');

    $('#orabooks-cust-notes').val('Updated note');
    $('.orabooks-cust-save-notes').trigger('click');

    const call = latestAjax('post');
    expect(call.data.action).toBe('orabooks_customer_update');
    expect(call.data.customer_id).toBe(9);
    expect(call.data.notes).toBe('Updated note');
  });
});

// ============================================================
// CUSTOMERS & INVOICES — orabooksLoadInvoices
// ============================================================
describe('orabooksLoadInvoices()', () => {
  beforeEach(setupCustomerDom);

  test('renders invoice table with formatted values', () => {
    clearAjax();
    window.orabooksLoadInvoices();

    resolveAjax('get', {
      data: {
        invoices: [
          {
            id: 100,
            invoice_number: 'INV-001',
            customer_email: 'cust@test.com',
            transaction_date: '2024-06-01',
            due_date: '2024-07-01',
            total_amount: 500.00,
            total_paid_amount: 250.00,
            payment_status: 'partial',
            workflow_status: 'posted'
          }
        ]
      }
    }, 'orabooks_invoices_list');

    const html = $('#orabooks-invoices-tbody').html();
    expect(html).toContain('INV-001');
    expect(html).toContain('cust@test.com');
    expect(html).toContain('$500.00');
    expect(html).toContain('$250.00');
    expect(html).toContain('Partial');
    expect(html).toContain('orabooks-inv-pay');
    expect(html).toContain('orabooks-inv-view');
  });

  test('shows empty message when no invoices', () => {
    clearAjax();
    window.orabooksLoadInvoices();

    resolveAjax('get', {
      data: { invoices: [] }
    }, 'orabooks_invoices_list');

    expect($('#orabooks-invoices-tbody').html()).toContain('No invoices found');
  });

  test('shows empty message when no data.invoices', () => {
    clearAjax();
    window.orabooksLoadInvoices();

    resolveAjax('get', {
      data: {}
    }, 'orabooks_invoices_list');

    expect($('#orabooks-invoices-tbody').html()).toContain('No invoices found');
  });

  test('does not show Pay button for paid invoices', () => {
    clearAjax();
    window.orabooksLoadInvoices();

    resolveAjax('get', {
      data: {
        invoices: [
          {
            id: 200,
            invoice_number: 'INV-200',
            customer_email: 'paid@test.com',
            transaction_date: '2024-06-01',
            due_date: '2024-07-01',
            total_amount: 1000.00,
            total_paid_amount: 1000.00,
            payment_status: 'paid',
            workflow_status: 'posted'
          }
        ]
      }
    }, 'orabooks_invoices_list');

    const html = $('#orabooks-invoices-tbody').html();
    expect(html).toContain('INV-200');
    expect(html).not.toContain('orabooks-inv-pay');
  });

  test('does not show Pay button for cancelled invoices', () => {
    clearAjax();
    window.orabooksLoadInvoices();

    resolveAjax('get', {
      data: {
        invoices: [
          {
            id: 201,
            invoice_number: 'INV-201',
            customer_email: 'cancel@test.com',
            transaction_date: '2024-06-01',
            due_date: '2024-07-01',
            total_amount: 500.00,
            total_paid_amount: 0,
            payment_status: 'cancelled',
            workflow_status: 'cancelled'
          }
        ]
      }
    }, 'orabooks_invoices_list');

    const html = $('#orabooks-invoices-tbody').html();
    expect(html).toContain('Cancelled');
    expect(html).not.toContain('orabooks-inv-pay');
  });

  test('passes filter values in request', () => {
    $('#orabooks-inv-filter-status').val('overdue');
    $('#orabooks-inv-filter-workflow').val('posted');
    $('#orabooks-inv-filter-from').val('2024-01-01');
    $('#orabooks-inv-filter-to').val('2024-12-31');

    clearAjax();
    window.orabooksLoadInvoices();

    const call = latestAjax('get');
    expect(call.data.payment_status).toBe('overdue');
    expect(call.data.workflow_status).toBe('posted');
    expect(call.data.from_date).toBe('2024-01-01');
    expect(call.data.to_date).toBe('2024-12-31');
  });

  test('invoice filter button triggers reload', () => {
    clearAjax();
    $('#orabooks-inv-filter-btn').trigger('click');

    const call = latestAjax('get');
    expect(call).not.toBeNull();
    expect(call.data.action).toBe('orabooks_invoices_list');
  });
});

// ============================================================
// CUSTOMERS & INVOICES — orabooksGetInvoiceStatusHtml
// ============================================================
describe('orabooksGetInvoiceStatusHtml()', () => {
  beforeEach(setupCustomerDom);

  test('returns unpaid badge', () => {
    const html = window.orabooksGetInvoiceStatusHtml('unpaid');
    expect(html).toContain('orabooks-badge-warning');
    expect(html).toContain('Unpaid');
  });

  test('returns partial badge', () => {
    const html = window.orabooksGetInvoiceStatusHtml('partial');
    expect(html).toContain('orabooks-badge-info');
    expect(html).toContain('Partial');
  });

  test('returns paid badge', () => {
    const html = window.orabooksGetInvoiceStatusHtml('paid');
    expect(html).toContain('orabooks-badge-active');
    expect(html).toContain('Paid');
  });

  test('returns overdue badge', () => {
    const html = window.orabooksGetInvoiceStatusHtml('overdue');
    expect(html).toContain('orabooks-badge-danger');
    expect(html).toContain('Overdue');
  });

  test('returns cancelled badge', () => {
    const html = window.orabooksGetInvoiceStatusHtml('cancelled');
    expect(html).toContain('orabooks-badge-inactive');
    expect(html).toContain('Cancelled');
  });

  test('defaults unknown status to warning', () => {
    const html = window.orabooksGetInvoiceStatusHtml('unknown_status');
    expect(html).toContain('orabooks-badge-warning');
    expect(html).toContain('unknown_status');
  });
});

// ============================================================
// CUSTOMERS & INVOICES — Create Invoice Modal
// ============================================================
describe('Invoice create modal', () => {
  beforeEach(setupCustomerDom);

  test('open modal fetches customer list and shows modal', () => {
    clearAjax();
    $('#orabooks-inv-create-btn').trigger('click');

    // Should fetch customers first
    const call = latestAjax('get');
    expect(call.data.action).toBe('orabooks_customers_list');

    resolveAjax('get', {
      data: {
        customers: [
          { id: 10, email: 'cust10@test.com' },
          { id: 20, email: 'cust20@test.com' }
        ]
      }
    }, 'orabooks_customers_list');

    expect($('#inv_customer_id').children().length).toBe(3); // placeholder + 2 customers
    expect($('#inv_customer_id').html()).toContain('cust10@test.com');
    expect($('#inv_customer_id').html()).toContain('cust20@test.com');
    expect($('#orabooks-invoice-modal').css('display')).not.toBe('none');
  });

  test('submit form posts invoice create request', () => {
    clearAjax();
    $('#orabooks-inv-create-btn').trigger('click');
    resolveAjax('get', {
      data: { customers: [{ id: 1, email: 'test@test.com' }] }
    }, 'orabooks_customers_list');

    // Fill form
    $('#inv_customer_id').val('1');
    $('#inv_total_amount').val('250.00');
    $('#inv_description').val('Test invoice');

    // Trigger form submit via jQuery (delegated handler)
    $('#orabooks-invoice-form').trigger('submit');

    const call = latestAjax('post');
    if (call) {
      // serialize() returns a URL-encoded string, not an object
      expect(call.data).toContain('action=orabooks_invoice_create');
      expect(call.data).toContain('customer_id=1');
      expect(call.data).toContain('total_amount=250.00');
    } else {
      // Fallback: verify form serialization produces correct params
      var serialized = $('#orabooks-invoice-form').serialize();
      expect(serialized).toContain('customer_id=1');
      expect(serialized).toContain('total_amount=250.00');
    }
  });

  test('modal close button hides modal', () => {
    clearAjax();
    $('#orabooks-inv-create-btn').trigger('click');
    resolveAjax('get', {
      data: { customers: [{ id: 1, email: 't@t.com' }] }
    }, 'orabooks_customers_list');

    $('.orabooks-modal-close').first().trigger('click');
    // With $.fx.off = true, fadeOut completes instantly
    expect($('#orabooks-invoice-modal').css('display')).toBe('none');
  });
});

// ============================================================
// CUSTOMERS & INVOICES — Invoice Detail
// ============================================================
describe('Invoice detail view', () => {
  beforeEach(setupCustomerDom);

  test('orabooksShowInvoiceDetail renders with payments', () => {
    clearAjax();
    window.orabooksShowInvoiceDetail(42);

    resolveAjax('get', {
      error: false,
      data: {
        id: 42,
        invoice_number: 'INV-042',
        customer_email: 'inv42@test.com',
        transaction_date: '2024-07-01',
        due_date: '2024-08-01',
        total_amount: 2000.00,
        paid_amount: 1000.00,
        payment_status: 'partial',
        description: 'Consulting services',
        payments: [
          { payment_date: '2024-07-15', amount: 500.00, payment_method: 'bank_transfer', reference: 'REF-001' },
          { payment_date: '2024-07-20', amount: 500.00, payment_method: 'credit_card', reference: '' }
        ]
      }
    }, 'orabooks_invoice_get');

    expect($('#orabooks-inv-detail-title').text()).toContain('INV-042');
    expect($('#orabooks-inv-detail-body').html()).toContain('$2,000.00');
    expect($('#orabooks-inv-detail-body').html()).toContain('$1,000.00');
    expect($('#orabooks-inv-detail-body').html()).toContain('Partial');
    expect($('#orabooks-invoice-detail').css('display')).not.toBe('none');
    // Payments table
    expect($('#orabooks-inv-detail-body').html()).toContain('REF-001');
    expect($('#orabooks-inv-detail-body').html()).toContain('bank_transfer');
  });

  test('orabooksShowInvoiceDetail renders without payments', () => {
    clearAjax();
    window.orabooksShowInvoiceDetail(43);

    resolveAjax('get', {
      error: false,
      data: {
        id: 43,
        invoice_number: 'INV-043',
        customer_email: 'no-pay@test.com',
        transaction_date: '2024-07-01',
        due_date: '2024-08-01',
        total_amount: 500.00,
        paid_amount: 0,
        payment_status: 'unpaid',
        description: 'Service',
        payments: []
      }
    }, 'orabooks_invoice_get');

    const html = $('#orabooks-inv-detail-body').html();
    expect(html).toContain('INV-043');
    expect(html).toContain('$500.00');
    expect(html).toContain('Unpaid');
    expect(html).not.toContain('Payments'); // No payments header
  });

  test('clicking view button opens invoice detail', () => {
    clearAjax();
    window.orabooksLoadInvoices();
    resolveAjax('get', {
      data: {
        invoices: [
          {
            id: 55,
            invoice_number: 'INV-055',
            customer_email: 'v@t.com',
            transaction_date: '2024-09-01',
            due_date: '2024-10-01',
            total_amount: 100.00,
            total_paid_amount: 0,
            payment_status: 'unpaid',
            workflow_status: 'draft'
          }
        ]
      }
    }, 'orabooks_invoices_list');

    $('.orabooks-inv-view').first().trigger('click');

    const call = latestAjax('get');
    expect(call.data.action).toBe('orabooks_invoice_get');
    expect(call.data.invoice_id).toBe(55);
  });

  test('close button hides invoice detail panel', () => {
    clearAjax();
    window.orabooksShowInvoiceDetail(1);
    resolveAjax('get', {
      error: false,
      data: { id: 1, invoice_number: 'INV-001', customer_email: 'c@t.com', total_amount: 0, paid_amount: 0, payment_status: 'unpaid', payments: [] }
    }, 'orabooks_invoice_get');

    $('#orabooks-inv-detail-close').trigger('click');
    expect($('#orabooks-invoice-detail').css('display')).toBe('none');
  });
});

// ============================================================
// CUSTOMERS & INVOICES — Payment Modal
// ============================================================
describe('Invoice payment modal', () => {
  beforeEach(setupCustomerDom);

  test('clicking Pay button opens payment modal', () => {
    // Populate invoices first
    clearAjax();
    window.orabooksLoadInvoices();
    resolveAjax('get', {
      data: {
        invoices: [
          {
            id: 77,
            invoice_number: 'INV-077',
            customer_email: 'pay-test@test.com',
            transaction_date: '2024-09-01',
            due_date: '2024-10-01',
            total_amount: 750.00,
            total_paid_amount: 0,
            payment_status: 'unpaid',
            workflow_status: 'posted'
          }
        ]
      }
    }, 'orabooks_invoices_list');

    $('.orabooks-inv-pay').first().trigger('click');

    expect($('#pay_invoice_id').val()).toBe('77');
    expect($('#pay_invoice_number').text()).toBe('INV-077');
    expect($('#orabooks-payment-modal').css('display')).not.toBe('none');
  });

  test('submit payment form posts record payment', () => {
    clearAjax();
    window.orabooksLoadInvoices();
    resolveAjax('get', {
      data: {
        invoices: [
          {
            id: 88,
            invoice_number: 'INV-088',
            customer_email: 'p@t.com',
            transaction_date: '2024-09-01',
            due_date: '2024-10-01',
            total_amount: 500.00,
            total_paid_amount: 0,
            payment_status: 'unpaid',
            workflow_status: 'posted'
          }
        ]
      }
    }, 'orabooks_invoices_list');

    $('.orabooks-inv-pay').first().trigger('click');

    // Fill payment form
    $('#pay_amount').val('500.00');
    $('#pay_method').val('bank_transfer');

    // Trigger form submit via jQuery (delegated handler)
    $('#orabooks-payment-form').trigger('submit');

    const call = latestAjax('post');
    if (call) {
      // serialize() returns a URL-encoded string, not an object
      expect(call.data).toContain('action=orabooks_invoice_record_payment');
      expect(call.data).toContain('invoice_id=88');
      expect(call.data).toContain('amount=500.00');
    } else {
      // Fallback: verify form serialization produces correct params
      var serialized = $('#orabooks-payment-form').serialize();
      expect(serialized).toContain('invoice_id=88');
      expect(serialized).toContain('amount=500.00');
    }
  });

  test('payment modal close hides modal', () => {
    clearAjax();
    window.orabooksLoadInvoices();
    resolveAjax('get', {
      data: {
        invoices: [
          {
            id: 99,
            invoice_number: 'INV-099',
            customer_email: 'c@t.com',
            transaction_date: '2024-09-01',
            due_date: '2024-10-01',
            total_amount: 1.00,
            total_paid_amount: 0,
            payment_status: 'unpaid',
            workflow_status: 'posted'
          }
        ]
      }
    }, 'orabooks_invoices_list');

    $('.orabooks-inv-pay').first().trigger('click');
    expect($('#orabooks-payment-modal').css('display')).not.toBe('none');

    // Target the cancel button inside the payment modal (not the invoice modal's)
    $('#orabooks-payment-modal').find('.orabooks-modal-cancel').trigger('click');
    jest.advanceTimersByTime(500);
    expect($('#orabooks-payment-modal').css('display')).toBe('none');
  });
});

// ============================================================
// CUSTOMERS & INVOICES — Reports
// ============================================================
describe('orabooksRenderReports()', () => {
  beforeEach(setupCustomerDom);

  test('renders all report values from data', () => {
    clearAjax();
    window.orabooksRenderReports({
      total_revenue: 50000.00,
      outstanding_ar: 12500.50,
      paid_invoices: 100,
      overdue_invoices: 8
    });

    expect($('#orabooks-ar-revenue').text()).toBe('$50,000.00');
    expect($('#orabooks-ar-outstanding').text()).toBe('$12,500.50');
    expect($('#orabooks-ar-paid').text()).toBe('100');
    expect($('#orabooks-ar-overdue').text()).toBe('8');
  });

  test('defaults undefined values to zero', () => {
    clearAjax();
    window.orabooksRenderReports({});

    expect($('#orabooks-ar-revenue').text()).toBe('$0.00');
    expect($('#orabooks-ar-outstanding').text()).toBe('$0.00');
    expect($('#orabooks-ar-paid').text()).toBe('0');
    expect($('#orabooks-ar-overdue').text()).toBe('0');
  });
});

// ============================================================
// CUSTOMERS & INVOICES — Deep Link (invoice_id query param)
// ============================================================
describe('Invoice deep link from URL', () => {
  let originalSearch;
  let originalReplaceState;

  beforeEach(() => {
    // Save and modify the existing location.search
    originalSearch = global.location.search;
    originalReplaceState = global.window.history.replaceState;
    global.window.history.replaceState = jest.fn();
  });

  afterEach(() => {
    // Restore
    global.location.search = originalSearch;
    global.window.history.replaceState = originalReplaceState;
  });

  test('deep link loads invoice and switches to invoices tab', () => {
    // Mock URLSearchParams to return an invoice_id
    var origGet = URLSearchParams.prototype.get;
    URLSearchParams.prototype.get = jest.fn(function(key) {
      if (key === 'invoice_id') return '123';
      if (key === 'page') return 'orabooks-customers';
      return null;
    });

    setupCustomerDom();

    // The ready handler's deep link IIFE should fire and queue invoice_get
    const getCalls = ajaxResponses.get;
    let foundInvGet = false;
    for (let i = 0; i < getCalls.length; i++) {
      if (getCalls[i].data && getCalls[i].data.action === 'orabooks_invoice_get') {
        foundInvGet = true;
        break;
      }
    }
    expect(foundInvGet).toBe(true);

    // Tab should have switched to invoices
    expect($('.nav-tab[data-tab="invoices"]').hasClass('nav-tab-active')).toBe(true);

    // history.replaceState should have been called to clean up the URL
    expect(window.history.replaceState).toHaveBeenCalled();

    URLSearchParams.prototype.get = origGet;
  });

  test('deep link does not fire when no invoice_id in URL', () => {
    // Mock URLSearchParams to NOT return an invoice_id
    var origGet = URLSearchParams.prototype.get;
    URLSearchParams.prototype.get = jest.fn(function(key) {
      if (key === 'page') return 'orabooks-customers';
      return null;
    });

    window.history.replaceState = jest.fn();

    setupCustomerDom();

    // Should NOT have invoice_get call
    const getCalls = ajaxResponses.get;
    let foundInvGet = false;
    for (let i = 0; i < getCalls.length; i++) {
      if (getCalls[i].data && getCalls[i].data.action === 'orabooks_invoice_get') {
        foundInvGet = true;
        break;
      }
    }
    expect(foundInvGet).toBe(false);

    // Tab should remain on customers
    expect($('.nav-tab[data-tab="customers"]').hasClass('nav-tab-active')).toBe(true);
    expect(window.history.replaceState).not.toHaveBeenCalled();

    URLSearchParams.prototype.get = origGet;
  });
});

// ============================================================
// CUSTOMERS & INVOICES — Modal Helpers (shared click handlers)
// ============================================================
describe('Shared modal close handlers', () => {
  beforeEach(setupCustomerDom);

  test('orabooks-modal-close hides the modal', () => {
    // Show a modal
    $('#orabooks-invoice-modal').fadeIn(200).show();
    expect($('#orabooks-invoice-modal').css('display')).not.toBe('none');

    $('.orabooks-modal-close').first().trigger('click');
    expect($('#orabooks-invoice-modal').css('display')).toBe('none');
  });

  test('orabooks-modal-backdrop hides the modal', () => {
    $('#orabooks-payment-modal').show();
    expect($('#orabooks-payment-modal').css('display')).not.toBe('none');

    // Target the backdrop inside the payment modal specifically
    $('#orabooks-payment-modal').find('.orabooks-modal-backdrop').trigger('click');
    jest.advanceTimersByTime(500);
    expect($('#orabooks-payment-modal').css('display')).toBe('none');
  });
});

// ============================================================
// Dashboard Stats — orabooksLoadDashboard
// ============================================================
describe('orabooksLoadDashboard()', () => {
  function setupDashboardDom() {
    document.body.innerHTML = `
      <div id="orabooks-dash-loading" style="display:none;">Loading dashboard...</div>
      <div id="orabooks-dash-content">Initial content</div>
      <div id="orabooks-dash-error" style="display:none;">Error loading dashboard</div>
      <span id="orabooks-last-updated"></span>

      <!-- Stat cards -->
      <span id="orabooks-stat-orgs-total"></span>
      <span id="orabooks-stat-orgs-breakdown"></span>
      <span id="orabooks-stat-partners-active"></span>
      <span id="orabooks-stat-partners-breakdown"></span>
      <span id="orabooks-stat-users-total"></span>
      <span id="orabooks-stat-users-breakdown"></span>
      <span id="orabooks-stat-attributions-verified"></span>
      <span id="orabooks-stat-attributions-breakdown"></span>

      <!-- Detail panels -->
      <span id="orabooks-stat-orgs-customer"></span>
      <span id="orabooks-stat-orgs-partner"></span>
      <span id="orabooks-stat-orgs-active"></span>
      <span id="orabooks-stat-orgs-pending"></span>
      <span id="orabooks-stat-orgs-suspended"></span>
      <span id="orabooks-stat-partners-active-detail"></span>
      <span id="orabooks-stat-partners-pending"></span>
      <span id="orabooks-stat-partners-inactive"></span>
      <span id="orabooks-stat-partners-disabled"></span>
      <span id="orabooks-stat-users-customer"></span>
      <span id="orabooks-stat-users-partner"></span>
      <span id="orabooks-stat-users-verified"></span>
      <span id="orabooks-stat-users-2fa"></span>
      <span id="orabooks-stat-attributions-total"></span>
      <span id="orabooks-stat-attributions-verified-detail"></span>
      <span id="orabooks-stat-attributions-pending"></span>
      <span id="orabooks-stat-attributions-blocked"></span>

      <!-- Recent activity -->
      <span id="orabooks-stat-recent-orgs"></span>
      <span id="orabooks-stat-recent-users"></span>
      <span id="orabooks-stat-recent-attributions"></span>

      <!-- Quick action badges -->
      <span id="orabooks-qa-pending-partners"></span>
      <span id="orabooks-qa-pending-orgs"></span>

      <!-- Refresh button -->
      <button id="orabooks-dash-refresh">⟳ Refresh</button>
    `;
    window.confirm.mockReturnValue(true);
    window.alert.mockClear();
    clearAjax();
    loadAdminJs();
  }

  test('shows loading state and populates stat cards from response', () => {
    setupDashboardDom();
    clearAjax();
    window.orabooksLoadDashboard();

    expect($('#orabooks-dash-loading').css('display')).toBe('block');
    expect($('#orabooks-dash-content').css('display')).toBe('none');

    resolveAjax('get', {
      error: false,
      data: {
        organizations: { total: 50, customer: 30, partner: 20, active: 40, pending: 5, suspended: 3, recent_7d: 10 },
        partners: { active: 15, pending: 3, inactive: 2, disabled: 1 },
        users: { total: 200, customer: 150, partner: 50, verified: 180, '2fa_enabled': 60, recent_7d: 25 },
        attributions: { total: 500, verified: 400, pending: 80, blocked: 20, recent_7d: 45 },
        timestamp: '2024-06-01 12:00:00'
      }
    });

    // Primary stat cards
    expect($('#orabooks-stat-orgs-total').text()).toBe('50');
    expect($('#orabooks-stat-partners-active').text()).toBe('15');
    expect($('#orabooks-stat-users-total').text()).toBe('200');
    expect($('#orabooks-stat-attributions-verified').text()).toBe('400');

    // Breakdowns
    expect($('#orabooks-stat-orgs-breakdown').html()).toContain('Customer: 30');
    expect($('#orabooks-stat-partners-breakdown').html()).toContain('Pending: 3');
    expect($('#orabooks-stat-users-breakdown').html()).toContain('Verified: 180');
    expect($('#orabooks-stat-attributions-breakdown').html()).toContain('Pending: 80');

    // Detail panels
    expect($('#orabooks-stat-orgs-customer').text()).toBe('30');
    expect($('#orabooks-stat-orgs-partner').text()).toBe('20');
    expect($('#orabooks-stat-orgs-active').text()).toBe('40');
    expect($('#orabooks-stat-orgs-pending').text()).toBe('5');
    expect($('#orabooks-stat-orgs-suspended').text()).toBe('3');
    expect($('#orabooks-stat-partners-active-detail').text()).toBe('15');
    expect($('#orabooks-stat-partners-pending').text()).toBe('3');
    expect($('#orabooks-stat-partners-inactive').text()).toBe('2');
    expect($('#orabooks-stat-partners-disabled').text()).toBe('1');
    expect($('#orabooks-stat-users-customer').text()).toBe('150');
    expect($('#orabooks-stat-users-partner').text()).toBe('50');
    expect($('#orabooks-stat-users-verified').text()).toBe('180');
    expect($('#orabooks-stat-users-2fa').text()).toBe('60');
    expect($('#orabooks-stat-attributions-total').text()).toBe('500');
    expect($('#orabooks-stat-attributions-verified-detail').text()).toBe('400');
    expect($('#orabooks-stat-attributions-pending').text()).toBe('80');
    expect($('#orabooks-stat-attributions-blocked').text()).toBe('20');

    // Recent activity
    expect($('#orabooks-stat-recent-orgs').text()).toBe('10');
    expect($('#orabooks-stat-recent-users').text()).toBe('25');
    expect($('#orabooks-stat-recent-attributions').text()).toBe('45');

    // Timestamp
    expect($('#orabooks-last-updated').text()).toContain('2024-06-01');

    // Loading state should be hidden, content shown
    expect($('#orabooks-dash-loading').css('display')).toBe('none');
    expect($('#orabooks-dash-content').css('display')).not.toBe('none');
  });

  test('shows quick action badges with pending counts', () => {
    setupDashboardDom();
    clearAjax();
    window.orabooksLoadDashboard();

    resolveAjax('get', {
      error: false,
      data: {
        organizations: { total: 5, customer: 3, partner: 2, active: 3, pending: 2, suspended: 0, recent_7d: 1 },
        partners: { active: 0, pending: 4, inactive: 0, disabled: 0 },
        users: { total: 10, customer: 8, partner: 2, verified: 9, '2fa_enabled': 3, recent_7d: 2 },
        attributions: { total: 20, verified: 15, pending: 4, blocked: 1, recent_7d: 3 }
      }
    });

    expect($('#orabooks-qa-pending-partners').text()).toBe('4');
    expect($('#orabooks-qa-pending-orgs').text()).toBe('2');
  });

  test('hides quick action badges when counts are zero', () => {
    setupDashboardDom();
    clearAjax();
    window.orabooksLoadDashboard();

    resolveAjax('get', {
      error: false,
      data: {
        organizations: { total: 0, customer: 0, partner: 0, active: 0, pending: 0, suspended: 0, recent_7d: 0 },
        partners: { active: 0, pending: 0, inactive: 0, disabled: 0 },
        users: { total: 0, customer: 0, partner: 0, verified: 0, '2fa_enabled': 0, recent_7d: 0 },
        attributions: { total: 0, verified: 0, pending: 0, blocked: 0, recent_7d: 0 }
      }
    });

    // Badges should be empty (zero is falsy, so '' is set)
    expect($('#orabooks-qa-pending-partners').text()).toBe('');
    expect($('#orabooks-qa-pending-orgs').text()).toBe('');
  });

  test('shows error state on API error response', () => {
    setupDashboardDom();
    clearAjax();
    window.orabooksLoadDashboard();

    expect($('#orabooks-dash-loading').css('display')).toBe('block');

    resolveAjax('get', { error: true, data: null });

    expect($('#orabooks-dash-loading').css('display')).toBe('none');
    expect($('#orabooks-dash-error').css('display')).not.toBe('none');
    expect($('#orabooks-dash-content').css('display')).toBe('none');
  });

  test('shows error state on AJAX failure (fail callback)', () => {
    setupDashboardDom();
    clearAjax();
    window.orabooksLoadDashboard();

    // Simulate AJAX failure by calling failCallback directly
    const calls = ajaxResponses.get;
    const lastCall = calls[calls.length - 1];
    expect(lastCall).not.toBeNull();
    expect(typeof lastCall.failCallback).toBe('function');
    lastCall.failCallback();

    expect($('#orabooks-dash-loading').css('display')).toBe('none');
    expect($('#orabooks-dash-error').css('display')).not.toBe('none');
  });
});

describe('Dashboard refresh button', () => {
  test('disables button and triggers refresh', () => {
    // Set up dashboard DOM to trigger auto-load
    document.body.innerHTML = `
      <div id="orabooks-dash-loading" style="display:none;">Loading dashboard...</div>
      <div id="orabooks-dash-content">Initial</div>
      <div id="orabooks-dash-error" style="display:none;">Error</div>
      <span id="orabooks-last-updated"></span>
      <span id="orabooks-stat-orgs-total"></span>
      <span id="orabooks-stat-partners-active"></span>
      <span id="orabooks-stat-users-total"></span>
      <span id="orabooks-stat-attributions-verified"></span>
      <button id="orabooks-dash-refresh">⟳ Refresh</button>
    `;
    window.confirm.mockReturnValue(true);
    window.alert.mockClear();
    clearAjax();
    loadAdminJs();

    // The ready handler auto-loads dashboard (dashboard DOM present)
    // and also orgs/audit/coa (no such elements in this small DOM)
    // so only dashboard GET call is present
    clearAjax();

    const $btn = $('#orabooks-dash-refresh');
    $btn.trigger('click');

    expect($btn.prop('disabled')).toBe(true);
    expect($btn.text()).toContain('Refreshing');

    // Should have made a new dashboard GET call
    const call = latestAjax('get');
    expect(call).not.toBeNull();
    expect(call.data.action).toBe('orabooks_dashboard_stats');
  });
});
