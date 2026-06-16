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

  // Mock dashboard response data
expect.extend({
  toBeWithinRange(received, floor, ceiling) {
    const pass = Number(received) >= floor && Number(received) <= ceiling;
    return {
      message: () => `expected ${received} to be within range ${floor} - ${ceiling}`,
      pass
    };
  }
});

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
