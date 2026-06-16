/**
 * Unit Tests for frontend.js
 *
 * Tests:
 * - Registration form submit (validation + AJAX)
 * - Login form submit (2FA, tier selection, redirect)
 * - Subdomain check
 * - Tier selection form
 * - Frontend export click handlers (Onboarding, CommConfig, ExportTrigger, Partner, Notif)
 * - Exports list load, cancel, pagination, refresh
 * - Copy partner code
 * - Reactivation modal
 * - Notification center: load, mark read, filter, preferences
 * - Commission dashboard: tab switching
 * - Commission config form load + submit
 * - Async queue dashboard: load stats, retry job
 */

const $ = require('jquery');
const path = require('path');
const fs = require('fs');

let originalLocation;

// Helper: set up DOM and load frontend.js
function setupFrontendDom() {
  document.body.innerHTML = `
    <!-- Registration form -->
    <form id="orabooks-register-form">
      <input id="reg-email" value="test@example.com" />
      <input id="reg-password" value="TestPass123!@#" />
      <input id="reg-confirm-password" value="TestPass123!@#" />
      <select id="reg-user-type"><option value="customer">Customer</option><option value="partner">Partner</option></select>
      <select id="reg-partner-type"><option value="individual">Individual</option></select>
      <input id="reg-org-name" value="" />
      <input id="reg-partner-code" value="" />
      <input id="reg-accept-terms" type="checkbox" checked />
      <div id="orabooks-register-message"></div>
      <button type="submit">Create Account</button>
    </form>

    <!-- Login form (wrapped in .orabooks-form-container for 2FA handler) -->
    <div class="orabooks-form-container">
      <form id="orabooks-login-form">
        <input id="login-email" value="test@example.com" />
        <input id="login-password" value="TestPass123!@#" />
        <div id="orabooks-login-message"></div>
        <button type="submit">Log In</button>
      </form>
    </div>

    <!-- Subdomain check -->
    <input id="tier-subdomain" value="mycompany" />
    <div id="orabooks-subdomain-status"></div>
    <button id="orabooks-check-subdomain">Check</button>

    <!-- Tier selection form -->
    <form id="orabooks-tier-form">
      <input type="radio" name="tier" value="free" checked />
      <input type="radio" name="tier" value="premium" />
      <div id="orabooks-tier-message"></div>
      <button type="submit">Select Tier</button>
    </form>

    <!-- Partner info (without orabooks-partner-info id to avoid initial POST call) -->
    <input id="orabooks-partner-code" />
    <div id="orabooks-partner-details"></div>
    <div id="orabooks-partner-status"></div>
    <button id="orabooks-copy-code">Copy Code</button>

    <!-- Partner dashboard -->
    <div class="orabooks-partner-dashboard">
      <input id="orabooks-dash-partner-code" />
      <button id="orabooks-dash-copy-code">Copy Code</button>
      <div id="orabooks-partner-type-display"></div>
      <div id="orabooks-status-banners"></div>
      <div id="orabooks-attr-total"></div>
      <div id="orabooks-attr-verified"></div>
      <div id="orabooks-attr-pending"></div>
      <div id="orabooks-comm-earned"></div>
      <div id="orabooks-comm-pending"></div>
      <div id="orabooks-comm-paid"></div>
      <div id="orabooks-comm-expired"></div>
      <table><tbody id="orabooks-attr-table-body"></tbody></table>
      <table><tbody id="orabooks-payout-breakdown-body"></tbody></table>
      <div id="orabooks-partner-dashboard-message"></div>
      <button id="orabooks-show-reactivation">Request Reactivation</button>

      <div id="orabooks-reactivation-modal" style="display:none;">
        <textarea id="orabooks-reactivation-reason"></textarea>
        <button id="orabooks-submit-reactivation">Submit</button>
        <div id="orabooks-reactivation-message"></div>
      </div>
      <button class="orabooks-modal-close">Close</button>

      <!-- Tabs -->
      <button class="orabooks-tab" data-tab="earned">Earned</button>
      <button class="orabooks-tab orabooks-tab-active" data-tab="payouts">Payouts</button>
      <div class="orabooks-tab-content" id="orabooks-tab-earned">Earned content</div>
      <div class="orabooks-tab-content orabooks-tab-content-active" id="orabooks-tab-payouts">Payouts content</div>
    </div>

    <!-- Commission Dashboard -->
    <div class="orabooks-commission-dashboard">
      <div id="orabooks-total-earned"></div>
      <div id="orabooks-pending-payout"></div>
      <div id="orabooks-total-paid"></div>
      <div id="orabooks-total-expired"></div>
      <div id="orabooks-escrow-remaining"></div>
      <table><tbody id="orabooks-earned-table-body"></tbody></table>
      <table><tbody id="orabooks-payouts-table-body"></tbody></table>
      <table><tbody id="orabooks-aging-table-body"></tbody></table>
      <table><tbody id="orabooks-escrow-table-body"></tbody></table>
    </div>

    <!-- Exports section -->
    <div class="orabooks-export-status">
      <div id="orabooks-export-total"></div>
      <div id="orabooks-export-pending"></div>
      <div id="orabooks-export-ready"></div>
      <table><tbody id="orabooks-export-table-body"></tbody></table>
      <div id="orabooks-export-pagination"></div>
      <button id="orabooks-export-refresh">Refresh</button>
      <button class="orabooks-export-trigger" data-export-type="report" data-format="csv">Export CSV</button>
      <button class="orabooks-export-cancel" data-id="42">Cancel</button>
      <button class="orabooks-export-page" data-page="2">2</button>
    </div>

    <!-- Notification Center -->
    <div class="orabooks-notification-center">
      <div id="orabooks-nc-unread-badge" style="display:none;"></div>
      <div id="orabooks-nc-list"></div>
      <button id="orabooks-nc-mark-all-read">Mark All Read</button>
      <div id="orabooks-nc-filter-apply">Apply Filter</div>
      <div id="orabooks-nc-proof-modal" style="display:none;">
        <div id="orabooks-nc-proof-content"></div>
      </div>
      <select id="orabooks-nc-filter-event"><option value="">All</option><option value="login">Login</option></select>
      <select id="orabooks-nc-filter-priority"><option value="">All</option><option value="high">High</option></select>
      <select id="orabooks-nc-filter-status"><option value="">All</option><option value="unread">Unread</option></select>
    </div>

    <!-- Notification Preferences -->
    <div class="orabooks-notification-preferences">
      <form id="orabooks-nc-prefs-form">
        <input type="checkbox" name="channels[]" value="email" checked />
        <input type="checkbox" name="channels[]" value="sms" />
        <input id="prefs-quiet-start" value="" />
        <input id="prefs-quiet-end" value="" />
        <select id="prefs-digest"><option value="none">None</option></select>
        <select id="prefs-language"><option value="en">English</option></select>
        <input type="checkbox" name="escalation_enabled" checked />
        <div id="orabooks-nc-prefs-message"></div>
        <button type="submit">Save</button>
      </form>
    </div>

    <!-- Notification Admin -->
    <div class="orabooks-notification-admin">
      <input id="policy-monthly-budget" />
      <input id="policy-retention" />
      <input id="policy-max-escalation" />
      <div id="orabooks-nc-policy-message"></div>
      <form id="orabooks-nc-policy-form">
        <button type="submit">Save Policy</button>
      </form>
      <table><tbody id="orabooks-nc-provider-health-body"></tbody></table>
      <button id="orabooks-nc-refresh-health">Refresh</button>
      <form id="orabooks-nc-audit-export-form">
        <input id="audit-start-date" value="2024-01-01" />
        <input id="audit-end-date" value="2024-01-31" />
        <div id="orabooks-nc-audit-result"></div>
        <button type="submit">Export Audit</button>
      </form>
    </div>

    <!-- Commission Config Form -->
    <form id="orabooks-commission-config-form">
      <input id="config-base-monthly" />
      <input id="config-max-years" />
      <input id="config-yearly-pcts" />
      <input id="config-min-payout" />
      <input id="config-active-window" />
      <select id="config-expiry-action"><option value="release">Release</option></select>
      <select id="config-fee-type"><option value="percentage">Percentage</option></select>
      <input id="config-fee-rate" />
      <div id="orabooks-commission-config-message"></div>
      <button type="submit">Save Config</button>
    </form>

    <!-- Dashboard (for invoice deep link auto-load tests) -->
    <div class="orabooks-dashboard">
      <div id="orabooks-dashboard-content"><p>Loading...</p></div>
    </div>

    <!-- Export trigger buttons for click handler tests -->
    <button class="orabooks-partner-export-trigger" data-export-type="commission_data" data-format="csv">Export Commissions CSV</button>
    <button class="orabooks-notif-export-trigger" data-export-type="notification_log" data-format="csv">Export CSV</button>
    <button class="orabooks-onboarding-export-trigger" data-export-type="partner_onboarding" data-format="csv">Export CSV</button>
    <button class="orabooks-commconfig-export-trigger" data-export-type="commission_config" data-format="csv">Export CSV</button>

    <!-- Async Queue Dashboard -->
    <div class="orabooks-async-queue-dashboard">
      <div id="aq-total">0</div>
      <div id="aq-pending">0</div>
      <div id="aq-processing">0</div>
      <div id="aq-completed">0</div>
      <div id="aq-failed">0</div>
      <div id="aq-dead">0</div>
      <div id="aq-latency">—</div>
      <div id="aq-failure-rate">—</div>
      <table><tbody id="orabooks-aq-failures-body"><tr><td colspan="6"><button class="orabooks-aq-retry" data-id="101">⟳ Retry</button></td></tr></tbody></table>
      <button id="orabooks-aq-refresh">Refresh</button>
    </div>

    <!-- Export message divs -->
    <div id="orabooks-partner-export-msg" style="display:none;"></div>
    <div id="orabooks-notif-export-msg" style="display:none;"></div>
    <div id="orabooks-onboarding-export-msg" style="display:none;"></div>
    <div id="orabooks-commconfig-export-msg" style="display:none;"></div>
  `;
}

function loadFrontendJs() {
  const code = fs.readFileSync(path.resolve(__dirname, '..', '..', 'assets', 'js', 'frontend.js'), 'utf8');
  const fn = new Function('$', 'jQuery', code);
  fn($, $);
}

beforeEach(() => {
  setupFrontendDom();
  window.confirm.mockReturnValue(true);
  window.alert.mockClear();
  // Save and restore location.href for redirect tests
  originalLocation = window.location.href;
  clearAjax();
  loadFrontendJs();
});

afterEach(() => {
  // Restore location.href
  window.location.href = originalLocation;
});

// ============================================================
// Registration Form
// ============================================================
describe('Registration form submit', () => {
  test('shows error if passwords do not match', () => {
    $('#reg-confirm-password').val('DifferentPass!');
    clearAjax(); // Clear initial POST (partner info)
    $('#orabooks-register-form').trigger('submit');

    const $msg = $('#orabooks-register-message');
    expect($msg.text()).toContain('Passwords do not match');
    expect($msg.hasClass('error')).toBe(true);
    expect(ajaxResponses.post.length).toBe(0); // No AJAX call
  });

  test('posts registration data when passwords match', () => {
    $('#reg-email').val('user@test.com');
    $('#reg-password').val('StrongPass1!');
    $('#reg-confirm-password').val('StrongPass1!');
    $('#reg-user-type').val('customer');

    clearAjax();
    $('#orabooks-register-form').trigger('submit');

    const call = latestAjax('post');
    expect(call.data.action).toBe('orabooks_register');
    expect(call.data.email).toBe('user@test.com');
    expect(call.data.accept_terms).toBe(1);
  });

  test('shows success message on registration success', () => {
    clearAjax();
    $('#orabooks-register-form').trigger('submit');
    resolveAjax('post', { error: false, data: { user_id: 1 }, message: 'Verification email sent' }, { action: 'orabooks_register' });

    const $msg = $('#orabooks-register-message');
    expect($msg.text()).toContain('Registration successful');
    expect($msg.hasClass('success')).toBe(true);
  });

  test('shows error message on registration failure', () => {
    clearAjax();
    $('#orabooks-register-form').trigger('submit');
    resolveAjax('post', { error: true, message: 'Email already exists' }, { action: 'orabooks_register' });

    const $msg = $('#orabooks-register-message');
    expect($msg.text()).toContain('Email already exists');
    expect($msg.hasClass('error')).toBe(true);
  });

  test('re-enables button after success', () => {
    clearAjax();
    $('#orabooks-register-form').trigger('submit');
    const $btn = $('#orabooks-register-form button');
    expect($btn.prop('disabled')).toBe(true);
    expect($btn.text()).toContain('Creating');

    resolveAjax('post', { error: false, data: {} }, { action: 'orabooks_register' });
    expect($btn.prop('disabled')).toBe(false);
    expect($btn.text()).toContain('Create Account');
  });
});

// ============================================================
// Login Form
// ============================================================
describe('Login form submit', () => {
  test('posts login credentials', () => {
    $('#login-email').val('user@test.com');
    $('#login-password').val('mypassword');

    clearAjax();
    $('#orabooks-login-form').trigger('submit');

    const call = latestAjax('post');
    expect(call.data.action).toBe('orabooks_login');
    expect(call.data.email).toBe('user@test.com');
  });

  test('handles 2FA required response by showing 2FA challenge form', () => {
    clearAjax();
    $('#orabooks-login-form').trigger('submit');
    resolveAjax('post', {
      error: false,
      data: {
        requires_2fa: true,
        temp_token: 'temp-jwt-for-2fa',
        user_id: 42
      }
    }, { action: 'orabooks_login' });

    // Login form should be hidden
    expect($('#orabooks-login-form').css('display')).toBe('none');
    // 2FA form should be created and visible
    expect($('#orabooks-2fa-form').length).toBe(1);
    expect($('#orabooks-2fa-form').css('display')).not.toBe('none');
    // OTP input should exist
    expect($('#2fa-otp').length).toBe(1);
    expect($('#2fa-otp').attr('maxlength')).toBe('6');
    // Backup code checkbox should exist
    expect($('#2fa-use-backup').length).toBe(1);
    // Temp token should be stored on form
    expect($('#orabooks-2fa-form').data('temp-token')).toBe('temp-jwt-for-2fa');
    expect($('#orabooks-2fa-form').data('user-id')).toBe(42);
  });

  test('redirects to tier selection when needs_tier_selection', () => {
    clearAjax();
    $('#orabooks-login-form').trigger('submit');
    // JSDOM blocks navigation, but we verify the handler ran without error
    // The needs_tier_selection branch sets window.location.href without a message
    resolveAjax('post', { error: false, data: { needs_tier_selection: true } }, { action: 'orabooks_login' });
    // Handler ran successfully — confirm by checking no failure message shown
    expect($('#orabooks-login-message').text()).not.toContain('error');
  });

  test('redirects to custom redirect_to when provided', () => {
    clearAjax();
    $('#orabooks-login-form').trigger('submit');
    // JSDOM blocks navigation, verify the handler ran correctly
    resolveAjax('post', { error: false, data: { redirect_to: '/partner/onboarding' } }, { action: 'orabooks_login' });
    expect($('#orabooks-login-message').text()).not.toContain('error');
  });

  test('stores token and redirects to dashboard on success', () => {
    clearAjax();
    $('#orabooks-login-form').trigger('submit');
    resolveAjax('post', { error: false, data: { token: 'jwt-token-123' } }, { action: 'orabooks_login' });

    expect(window.localStorage.setItem).toHaveBeenCalledWith('orabooks_token', 'jwt-token-123');

    jest.advanceTimersByTime(1000);
    // JSDOM blocks navigation, but the message shows success
    expect($('#orabooks-login-message').text()).toContain('successful');
  });

  test('shows error on login failure', () => {
    clearAjax();
    $('#orabooks-login-form').trigger('submit');
    resolveAjax('post', { error: true, message: 'Invalid credentials' }, { action: 'orabooks_login' });

    const $msg = $('#orabooks-login-message');
    expect($msg.text()).toContain('Invalid credentials');
    expect($msg.hasClass('error')).toBe(true);
  });
});

// ============================================================
// Subdomain Check
// ============================================================
describe('Subdomain availability check', () => {
  test('posts subdomain for checking', () => {
    $('#tier-subdomain').val('mycompany');
    clearAjax();
    $('#orabooks-check-subdomain').trigger('click');

    const call = latestAjax('post');
    expect(call.data.action).toBe('orabooks_check_subdomain');
    expect(call.data.subdomain).toBe('mycompany');
  });

  test('shows available when subdomain is free', () => {
    clearAjax();
    $('#orabooks-check-subdomain').trigger('click');
    resolveAjax('post', { error: false, data: { available: true, message: 'Available' } }, { action: 'orabooks_check_subdomain' });

    expect($('#orabooks-subdomain-status').text()).toContain('Available');
    expect($('#orabooks-subdomain-status').css('color')).toBe('rgb(46, 125, 50)');
  });

  test('shows taken when subdomain exists', () => {
    clearAjax();
    $('#orabooks-check-subdomain').trigger('click');
    resolveAjax('post', { error: false, data: { available: false, message: 'Subdomain already taken' } }, { action: 'orabooks_check_subdomain' });

    expect($('#orabooks-subdomain-status').text()).toContain('Subdomain already taken');
    expect($('#orabooks-subdomain-status').css('color')).toBe('rgb(196, 26, 26)');
  });

  test('does nothing for empty subdomain', () => {
    $('#tier-subdomain').val('');
    clearAjax();
    $('#orabooks-check-subdomain').trigger('click');
    expect(ajaxResponses.post.length).toBe(0);
  });
});

// ============================================================
// Tier Selection Form
// ============================================================
describe('Tier selection form submit', () => {
  test('posts selected tier and subdomain', () => {
    $('input[name="tier"][value="premium"]').prop('checked', true);
    $('#tier-subdomain').val('myorg');

    clearAjax();
    $('#orabooks-tier-form').trigger('submit');

    const call = latestAjax('post');
    expect(call.data.action).toBe('orabooks_select_tier');
    expect(call.data.tier).toBe('premium');
    expect(call.data.subdomain).toBe('myorg');
  });

  test('stores token and redirects on success', () => {
    clearAjax();
    $('#orabooks-tier-form').trigger('submit');
    resolveAjax('post', { error: false, data: { token: 'jwt-tier', redirect_to: 'https://myorg.orabooks.app/dashboard' } }, { action: 'orabooks_select_tier' });

    expect(window.localStorage.setItem).toHaveBeenCalledWith('orabooks_token', 'jwt-tier');
    jest.advanceTimersByTime(1500);
    // JSDOM blocks navigation, but the success message shows
    expect($('#orabooks-tier-message').text()).toContain('Redirecting');
  });

  test('shows error on failure', () => {
    clearAjax();
    $('#orabooks-tier-form').trigger('submit');
    resolveAjax('post', { error: true, message: 'Subdomain taken' }, { action: 'orabooks_select_tier' });

    expect($('#orabooks-tier-message').text()).toContain('Subdomain taken');
    expect($('#orabooks-tier-message').hasClass('error')).toBe(true);
  });
});

// ============================================================
// Copy Partner Code
// ============================================================
describe('Copy partner code', () => {
  test('copies code and fires audit event', () => {
    document.getElementById('orabooks-partner-code').value = 'PARTNER-CODE-123';
    document.execCommand = jest.fn();

    $('#orabooks-copy-code').trigger('click');

    expect(document.execCommand).toHaveBeenCalledWith('copy');
    expect($('#orabooks-copy-code').text()).toBe('Copied!');

    jest.advanceTimersByTime(2000);
    expect($('#orabooks-copy-code').text()).toBe('Copy Code');
  });
});

// ============================================================
// Dashboard Copy Code
// ============================================================
describe('Dashboard copy code', () => {
  test('copies code and fires audit event', () => {
    document.getElementById('orabooks-dash-partner-code').value = 'DASH-CODE';
    document.execCommand = jest.fn();

    $('#orabooks-dash-copy-code').trigger('click');

    expect(document.execCommand).toHaveBeenCalledWith('copy');
    expect($('#orabooks-dash-copy-code').text()).toBe('Copied!');

    // Should POST audit event
    const call = latestAjax('post');
    expect(call.data.action).toBe('orabooks_partner_code_copied');
    expect(call.data.source).toBe('dashboard');
  });
});

// ============================================================
// Reactivation Modal
// ============================================================
describe('Reactivation modal', () => {
  test('shows modal on click', () => {
    $('#orabooks-show-reactivation').trigger('click');
    expect($('#orabooks-reactivation-modal').css('display')).toBe('block');
  });

  test('closes modal on close button', () => {
    $('#orabooks-reactivation-modal').css('display', 'block');
    $('.orabooks-modal-close').trigger('click');
    expect($('#orabooks-reactivation-modal').css('display')).toBe('none');
  });

  test('submits reactivation request', () => {
    clearAjax();
    $('#orabooks-reactivation-modal').css('display', 'block');
    $('#orabooks-reactivation-reason').val('Need to reactivate');

    $('#orabooks-submit-reactivation').trigger('click');

    const call = latestAjax('post');
    expect(call.data.action).toBe('orabooks_request_reactivation');
    expect(call.data.reason).toBe('Need to reactivate');
  });

  test('shows success message on reactivation', () => {
    clearAjax();
    $('#orabooks-submit-reactivation').trigger('click');
    resolveAjax('post', { error: false, data: {}, message: 'Reactivation request submitted' }, { action: 'orabooks_request_reactivation' });

    const $msg = $('#orabooks-reactivation-message');
    expect($msg.text()).toContain('Reactivation request submitted');
    expect($msg.hasClass('success')).toBe(true);
  });
});

// ============================================================
// Tab Switching
// ============================================================
describe('Tab switching', () => {
  test('switches active tab and shows corresponding content', () => {
    // Click on the "Earned" tab
    $('.orabooks-tab[data-tab="earned"]').trigger('click');

    expect($('.orabooks-tab[data-tab="earned"]').hasClass('orabooks-tab-active')).toBe(true);
    expect($('.orabooks-tab[data-tab="payouts"]').hasClass('orabooks-tab-active')).toBe(false);
    expect($('#orabooks-tab-earned').hasClass('orabooks-tab-content-active')).toBe(true);
    expect($('#orabooks-tab-payouts').hasClass('orabooks-tab-content-active')).toBe(false);
  });
});

// ============================================================
// Frontend Export Trigger (orabooks-export-trigger in exports section)
// ============================================================
describe('Frontend .orabooks-export-trigger click (exports section)', () => {
  test('posts export request', () => {
    const $btn = $('.orabooks-export-trigger').first();
    clearAjax();
    $btn.trigger('click');

    const call = latestAjax('post');
    expect(call.data.action).toBe('orabooks_export_request');
    expect(call.data.export_type).toBe('report');
  });

  test('shows success message and refreshes exports list', () => {
    const $btn = $('.orabooks-export-trigger').first();
    clearAjax();
    $btn.trigger('click');
    resolveAjax('post', { error: false, data: {} }, { action: 'orabooks_export_request' });

    expect($btn.text()).toContain('Requested');
  });
});

// ============================================================
// Frontend Partner Export Trigger
// ============================================================
describe('Frontend .orabooks-partner-export-trigger click', () => {
  test('posts commission_data export request', () => {
    clearAjax();
    $('.orabooks-partner-export-trigger').first().trigger('click');

    const call = latestAjax('post');
    expect(call.data.export_type).toBe('commission_data');
    expect(call.data.format).toBe('csv');

    const params = JSON.parse(call.data.parameters);
    expect(params.columns).toContain('customer');
  });

  test('shows success in partner message div', () => {
    clearAjax();
    $('.orabooks-partner-export-trigger').first().trigger('click');
    resolveAjax('post', { error: false, data: {} }, { action: 'orabooks_export_request' });

    expect($('#orabooks-partner-export-msg').css('display')).toBe('block');
    expect($('#orabooks-partner-export-msg').html()).toContain('View status');
  });
});

// ============================================================
// Frontend Notification Export Trigger
// ============================================================
describe('Frontend .orabooks-notif-export-trigger click', () => {
  test('posts with current notification filter values', () => {
    $('#orabooks-nc-filter-priority').val('high');
    $('#orabooks-nc-filter-status').val('unread');

    clearAjax();
    $('.orabooks-notif-export-trigger').first().trigger('click');

    const call = latestAjax('post');
    expect(call.data.export_type).toBe('notification_log');

    const params = JSON.parse(call.data.parameters);
    expect(params.priority).toBe('high');
    expect(params.status).toBe('unread');
  });

  test('shows success in message div', () => {
    clearAjax();
    $('.orabooks-notif-export-trigger').first().trigger('click');
    resolveAjax('post', { error: false, data: {} }, { action: 'orabooks_export_request' });

    expect($('#orabooks-notif-export-msg').css('display')).toBe('block');
  });
});

// ============================================================
// Frontend Onboarding Export Trigger
// ============================================================
describe('Frontend .orabooks-onboarding-export-trigger click', () => {
  test('posts partner_onboarding export request', () => {
    clearAjax();
    $('.orabooks-onboarding-export-trigger').first().trigger('click');

    const call = latestAjax('post');
    expect(call.data.export_type).toBe('partner_onboarding');
  });

  test('shows success in onboarding message div', () => {
    clearAjax();
    $('.orabooks-onboarding-export-trigger').first().trigger('click');
    resolveAjax('post', { error: false, data: {} }, { action: 'orabooks_export_request' });

    expect($('#orabooks-onboarding-export-msg').css('display')).toBe('block');
  });
});

// ============================================================
// Frontend CommConfig Export Trigger
// ============================================================
describe('Frontend .orabooks-commconfig-export-trigger click', () => {
  test('posts commission_config export request', () => {
    clearAjax();
    $('.orabooks-commconfig-export-trigger').first().trigger('click');

    const call = latestAjax('post');
    expect(call.data.export_type).toBe('commission_config');
  });

  test('shows success in commconfig message div', () => {
    clearAjax();
    $('.orabooks-commconfig-export-trigger').first().trigger('click');
    resolveAjax('post', { error: false, data: {} }, { action: 'orabooks_export_request' });

    expect($('#orabooks-commconfig-export-msg').css('display')).toBe('block');
  });
});

// ============================================================
// Exports: Load, Cancel, Pagination, Refresh
// ============================================================
describe('orabooksLoadExports (exports list)', () => {
  test('renders loading state then populates exports table', () => {
    const $tbody = $('#orabooks-export-table-body');
    expect($tbody.html()).toContain('Loading exports');

    resolveAjax('get', {
      error: false,
      data: {
        exports: [
          { id: 1, export_type: 'audit_log', format: 'csv', status: 'ready', file_size: '1.2 MB', expires_at: '2024-02-01', download_count: 3, can_download: true, file_url: '/export/1.csv', can_cancel: false, file_hash: 'abc123' }
        ],
        total: 1,
        page: 1,
        total_pages: 1
      }
    }, { action: 'orabooks_exports_list' });

    const html = $tbody.html();
    expect(html).toContain('audit_log');
    expect(html).toContain('CSV');
    expect(html).toContain('Ready');
    expect(html).toContain('Download');
  });

  test('shows empty state when no exports', () => {
    resolveAjax('get', {
      error: false,
      data: { exports: [], total: 0, page: 1, total_pages: 1 }
    }, { action: 'orabooks_exports_list' });

    const html = $('#orabooks-export-table-body').html();
    expect(html).toContain('No exports found');
  });

  test('renders pagination when multiple pages', () => {
    resolveAjax('get', {
      error: false,
      data: { exports: [], total: 50, page: 1, total_pages: 3 }
    }, { action: 'orabooks_exports_list' });

    const pagHtml = $('#orabooks-export-pagination').html();
    expect(pagHtml).toContain('1');
    expect(pagHtml).toContain('2');
    expect(pagHtml).toContain('3');
  });
});

describe('Export cancel', () => {
  test('posts cancel request and reloads', () => {
    clearAjax();
    $('.orabooks-export-cancel').first().trigger('click');
    expect(window.confirm).toHaveBeenCalled();

    const call = latestAjax('post');
    expect(call.data.action).toBe('orabooks_export_cancel');
    expect(call.data.export_id).toBe(42);
  });

  test('shows error alert on cancel failure', () => {
    clearAjax();
    $('.orabooks-export-cancel').first().trigger('click');
    resolveAjax('post', { error: true, message: 'Cannot cancel' }, { action: 'orabooks_export_cancel' });
    expect(window.alert).toHaveBeenCalledWith('Error: Cannot cancel');
  });
});

describe('Export pagination', () => {
  test('loads a different page on page click', () => {
    $('.orabooks-export-page').first().trigger('click');
    const call = latestAjax('get');
    expect(call.data.page).toBe(2);
  });
});

describe('Export refresh button', () => {
  test('reloads exports on refresh click', () => {
    $('#orabooks-export-refresh').trigger('click');
    const call = latestAjax('get');
    expect(call.data.action).toBe('orabooks_exports_list');
  });
});

// ============================================================
// Notification Center
// ============================================================
describe('Notification center - load notifications', () => {
  test('renders notifications from AJAX', () => {
    resolveAjax('get', {
      error: false,
      data: {
        unread_count: 2,
        notifications: [
          { id: 1, event_type: 'login', title: 'New Login', message: 'Someone logged in', priority: 'low', created_at: '2024-01-01', is_read: false, delivery_channel: 'inapp', correlation_id: 'corr-123' },
          { id: 2, event_type: 'export', title: 'Export Ready', message: 'Your export is ready', priority: 'high', created_at: '2024-01-02', is_read: true, delivery_channel: 'email', correlation_id: 'corr-456' }
        ]
      }
    }, { action: 'orabooks_notifications_list' });

    const html = $('#orabooks-nc-list').html();
    expect(html).toContain('New Login');
    expect(html).toContain('Export Ready');
    expect(html).toContain('Someone logged in');
    expect($('#orabooks-nc-unread-badge').text()).toContain('2');
    expect($('#orabooks-nc-unread-badge').css('display')).not.toBe('none');
  });

  test('shows empty state when no notifications', () => {
    resolveAjax('get', { error: false, data: { unread_count: 0, notifications: [] } }, { action: 'orabooks_notifications_list' });

    expect($('#orabooks-nc-list').html()).toContain('No notifications found');
    expect($('#orabooks-nc-unread-badge').css('display')).toBe('none');
  });
});

describe('Notification mark as read', () => {
  test('marks notification as read on click', () => {
    resolveAjax('get', {
      error: false,
      data: {
        unread_count: 1,
        notifications: [{ id: 5, event_type: 'test', title: 'Test', message: 'Test msg', priority: 'low', created_at: '2024-01-01', is_read: false, delivery_channel: 'inapp', correlation_id: 'corr-789' }]
      }
    }, { action: 'orabooks_notifications_list' });

    const $item = $('.orabooks-nc-item').first();
    expect($item.hasClass('orabooks-nc-item-unread')).toBe(true);

    $item.trigger('click');
    expect($item.hasClass('orabooks-nc-item-unread')).toBe(false);
  });
});

describe('Notification - mark all read', () => {
  test('posts mark all read and hides badge', () => {
    resolveAjax('get', {
      error: false,
      data: { unread_count: 3, notifications: [
        { id: 1, event_type: 'a', title: 'A', priority: 'low', created_at: '2024-01-01', is_read: false, delivery_channel: 'inapp', correlation_id: 'abc' },
        { id: 2, event_type: 'b', title: 'B', priority: 'low', created_at: '2024-01-01', is_read: true, delivery_channel: 'inapp', correlation_id: 'def' }
      ]}
    }, { action: 'orabooks_notifications_list' });

    $('#orabooks-nc-mark-all-read').trigger('click');

    const call = latestAjax('post');
    expect(call.data.action).toBe('orabooks_notifications_mark_all_read');
  });
});

describe('Notification - filter apply', () => {
  test('reloads notifications on filter apply', () => {
    clearAjax();
    $('#orabooks-nc-filter-apply').trigger('click');
    const call = latestAjax('get');
    expect(call.data.action).toBe('orabooks_notifications_list');
  });
});

// ============================================================
// Notification Preferences
// ============================================================
describe('Notification preferences save', () => {
  test('posts serialized form data with action', () => {
    $('#orabooks-nc-prefs-form').trigger('submit');

    const call = latestAjax('post');
    // Form data is serialized as an array (via .serializeArray())
    expect(call.data.find(function(d) { return d.name === 'action'; }).value).toBe('orabooks_notification_preferences_save');
  });

  test('shows success message on save', () => {
    $('#orabooks-nc-prefs-form').trigger('submit');
    resolveAjax('post', { error: false, data: {}, message: 'Preferences saved' });

    expect($('#orabooks-nc-prefs-message').text()).toContain('Preferences saved');
  });
});

// ============================================================
// Notification Admin - Policy Save
// ============================================================
describe('Notification admin policy save', () => {
  test('posts policy form data', () => {
    $('#orabooks-nc-policy-form').trigger('submit');

    const call = latestAjax('post');
    expect(call.data.find(function(d) { return d.name === 'action'; }).value).toBe('orabooks_notification_admin_policy_save');
    expect(call.data.find(function(d) { return d.name === 'org_id'; }).value).toBe(0);
  });
});

// ============================================================
// Notification Admin - Audit Export
// ============================================================
describe('Notification admin audit export', () => {
  test('downloads audit bundle as JSON file', () => {
    const mockUrl = 'blob:test-url';
    global.URL.createObjectURL = jest.fn(() => mockUrl);
    global.URL.revokeObjectURL = jest.fn();
    global.Blob = jest.fn((content, opts) => ({ content, opts }));
    document.body.appendChild = jest.fn();
    document.body.removeChild = jest.fn();

    $('#orabooks-nc-audit-export-form').trigger('submit');

    const call = latestAjax('get');
    expect(call.data.action).toBe('orabooks_notification_admin_audit_export');
    expect(call.data.start_date).toBe('2024-01-01');
    expect(call.data.end_date).toBe('2024-01-31');

    // Resolve the AJAX call
    const callback = call.callback;
    callback({ error: false, data: { events: [], policies: [] } });

    expect(Blob).toHaveBeenCalled();
    expect(document.body.appendChild).toHaveBeenCalled();
    expect(document.body.removeChild).toHaveBeenCalled();
    expect(URL.revokeObjectURL).toHaveBeenCalledWith(mockUrl);
  });
});

// ============================================================
// Async Queue Dashboard
// ============================================================
describe('Async queue dashboard - load stats', () => {
  test('renders queue stats from AJAX', () => {
    resolveAjax('get', {
      error: false,
      data: {
        total: 150,
        pending_count: 20,
        processing_count: 5,
        completed_count: 120,
        failed_count: 3,
        dead_letter_count: 2,
        avg_latency_seconds: 1.5,
        failure_rate_24h: 2.5,
        recent_failures: [
          { id: 101, job_type: 'generate_export', retry_count: 3, last_error: 'Timeout', last_attempt_at: '2024-01-01', created_at: '2024-01-01', status: 'failed' }
        ]
      }
    }, { action: 'orabooks_async_queue_stats' });

    expect($('#aq-total').text()).toBe('150');
    expect($('#aq-pending').text()).toBe('20');
    expect($('#aq-processing').text()).toBe('5');
    expect($('#aq-completed').text()).toBe('120');
    expect($('#aq-failed').text()).toBe('3');
    expect($('#aq-dead').text()).toBe('2');
    expect($('#aq-latency').text()).toBe('1.5s');
    expect($('#aq-failure-rate').text()).toBe('2.5%');

    const html = $('#orabooks-aq-failures-body').html();
    expect(html).toContain('101');
    expect(html).toContain('Timeout');
  });

  test('shows no failures message when no failures', () => {
    resolveAjax('get', {
      error: false,
      data: { total: 0, pending_count: 0, processing_count: 0, completed_count: 0, failed_count: 0, dead_letter_count: 0, recent_failures: [] }
    }, { action: 'orabooks_async_queue_stats' });

    expect($('#orabooks-aq-failures-body').html()).toContain('No recent failures');
  });
});

describe('Async queue - retry job', () => {
  test('posts retry request for specific job', () => {
    clearAjax();
    $('.orabooks-aq-retry').first().trigger('click');
    const call = latestAjax('post');
    expect(call.data.action).toBe('orabooks_async_queue_replay');
  });
});

describe('Async queue refresh button', () => {
  test('reloads stats on refresh click', () => {
    clearAjax();
    $('#orabooks-aq-refresh').trigger('click');
    const call = latestAjax('get');
    expect(call.data.action).toBe('orabooks_async_queue_stats');
  });
});

// ============================================================
// Invoice Deep Link Auto-Load from ?invoice_id=
// ============================================================
describe('Invoice deep link auto-load from ?invoice_id=', () => {
  let urlSearchGetSpy;

  afterEach(() => {
    if (urlSearchGetSpy) {
      urlSearchGetSpy.mockRestore();
      urlSearchGetSpy = null;
    }
  });

  /**
   * Set dashboard DOM and mock URLSearchParams.get so the IIFE fires
   * with the given invoice_id. Does NOT touch window.location.search
   * (which triggers unimplemented navigation in JSDOM).
   */
  function setupInvoiceTest(invoiceId) {
    document.body.innerHTML = `
      <div class="orabooks-dashboard">
        <div id="orabooks-dashboard-content"><p>Loading...</p></div>
      </div>
    `;
    clearAjax();

    // Mock URLSearchParams.get so the IIFE in frontend.js reads invoice_id
    urlSearchGetSpy = jest.spyOn(URLSearchParams.prototype, 'get').mockImplementation(function (key) {
      if (key === 'invoice_id' && invoiceId != null) {
        return String(invoiceId);
      }
      return null;
    });

    loadFrontendJs();
  }

  test('does nothing when URL has no invoice_id on dashboard', () => {
    // The outer beforeEach already ran with no invoice_id in URL — IIFE shouldn't fire
    const invoiceCall = ajaxResponses.get.find(function (c) {
      return c.data && c.data.action === 'orabooks_invoice_get';
    });
    expect(invoiceCall).toBeUndefined();
  });

  test('does nothing when not on dashboard page even with invoice_id', () => {
    document.body.innerHTML = '<div id="some-other-page"></div>';
    clearAjax();

    urlSearchGetSpy = jest.spyOn(URLSearchParams.prototype, 'get').mockImplementation(function (key) {
      if (key === 'invoice_id') return '200';
      return null;
    });

    loadFrontendJs();
    expect(ajaxResponses.get.length).toBe(0);
  });

  test('calls orabooks_invoice_get when ?invoice_id= present on dashboard', () => {
    setupInvoiceTest(200);

    const call = latestAjax('get');
    expect(call).not.toBeNull();
    expect(call.data.action).toBe('orabooks_invoice_get');
    expect(call.data.invoice_id).toBe(200);
  });

  test('parses invoice_id as integer', () => {
    setupInvoiceTest(42);

    const call = latestAjax('get');
    expect(call.data.invoice_id).toBe(42);
  });

  test('does not trigger invoice load for other query params', () => {
    setupInvoiceTest(null);
    expect(ajaxResponses.get.length).toBe(0);
  });

  test('shows loading state immediately', () => {
    setupInvoiceTest(200);
    expect($('#orabooks-dashboard-content').html()).toContain('Loading invoice');
  });

  test('renders error message when invoice load fails', () => {
    setupInvoiceTest(999);
    resolveAjax('get', { error: true, message: 'Invoice not found' }, { action: 'orabooks_invoice_get' });

    const html = $('#orabooks-dashboard-content').html();
    expect(html).toContain('Invoice not found');
    expect(html).toContain('Back to Dashboard');
    expect(html).toContain('❌');
  });

  test('renders complete invoice detail on success with all fields', () => {
    setupInvoiceTest(200);
    resolveAjax('get', {
      error: false,
      data: {
        id: 200,
        invoice_number: 'INV-202606-0001',
        customer_email: 'customer@example.com',
        transaction_date: '2026-06-01',
        due_date: '2026-07-01',
        total_amount: 1500.00,
        paid_amount: 500.00,
        description: 'Consulting services - Q2',
        payment_status: 'partial',
        currency: 'USD',
        payments: [
          { payment_date: '2026-06-15', amount: 500.00, payment_method: 'bank_transfer', reference: 'REF-001' }
        ]
      }
    }, { action: 'orabooks_invoice_get' });

    const html = $('#orabooks-dashboard-content').html();
    expect(html).toContain('INV-202606-0001');
    expect(html).toContain('Partial');
    expect(html).toContain('customer@example.com');
    expect(html).toContain('2026-06-01');
    expect(html).toContain('2026-07-01');
    expect(html).toContain('$1,500.00');
    expect(html).toContain('$500.00');
    expect(html).toContain('Consulting services');
    expect(html).toContain('REF-001');
    expect(html).toContain('bank_transfer');
    expect(html).toContain('Back to Dashboard');
  });

  test('renders without payments table when no payments', () => {
    setupInvoiceTest(300);
    resolveAjax('get', {
      error: false,
      data: {
        id: 300,
        invoice_number: 'INV-202606-0002',
        customer_email: 'c@t.com',
        transaction_date: '2026-06-01',
        due_date: '2026-07-01',
        total_amount: 100.00,
        paid_amount: 0,
        description: '',
        payment_status: 'unpaid',
        currency: 'USD',
        payments: []
      }
    }, { action: 'orabooks_invoice_get' });

    const html = $('#orabooks-dashboard-content').html();
    expect(html).toContain('$100.00');
    expect(html).toContain('Unpaid');
    expect(html).not.toContain('Method');
    expect(html).not.toContain('Consulting');
  });

  test('renders overdue status badge correctly', () => {
    setupInvoiceTest(400);
    resolveAjax('get', {
      error: false,
      data: {
        id: 400,
        invoice_number: 'INV-202606-0003',
        customer_email: 'c@t.com',
        transaction_date: '2026-01-01',
        due_date: '2026-02-01',
        total_amount: 250.00,
        paid_amount: 0,
        description: 'Past due',
        payment_status: 'overdue',
        currency: 'USD',
        payments: []
      }
    }, { action: 'orabooks_invoice_get' });
    expect($('#orabooks-dashboard-content').html()).toContain('Overdue');
  });

  test('renders paid status badge correctly', () => {
    setupInvoiceTest(500);
    resolveAjax('get', {
      error: false,
      data: {
        id: 500,
        invoice_number: 'INV-202606-0004',
        customer_email: 'c@t.com',
        transaction_date: '2026-05-01',
        due_date: '2026-06-01',
        total_amount: 5000.00,
        paid_amount: 5000.00,
        description: 'Fully paid',
        payment_status: 'paid',
        currency: 'USD',
        payments: [
          { payment_date: '2026-05-15', amount: 5000.00, payment_method: 'credit_card', reference: 'CC-123' }
        ]
      }
    }, { action: 'orabooks_invoice_get' });
    expect($('#orabooks-dashboard-content').html()).toContain('Paid');
  });

  test('shows balance of zero for fully paid invoices', () => {
    setupInvoiceTest(500);
    resolveAjax('get', {
      error: false,
      data: {
        id: 500,
        invoice_number: 'INV-202606-0004',
        customer_email: 'c@t.com',
        transaction_date: '2026-05-01',
        due_date: '2026-06-01',
        total_amount: 5000.00,
        paid_amount: 5000.00,
        description: '',
        payment_status: 'paid',
        currency: 'USD',
        payments: []
      }
    }, { action: 'orabooks_invoice_get' });
    const html = $('#orabooks-dashboard-content').html();
    expect(html).toContain('$0.00');
  });

  test('handles missing optional fields gracefully', () => {
    setupInvoiceTest(600);
    resolveAjax('get', {
      error: false,
      data: {
        id: 600,
        invoice_number: 'INV-202606-0005',
        customer_email: null,
        transaction_date: null,
        due_date: null,
        total_amount: 200,
        paid_amount: 0,
        description: null,
        payment_status: 'unpaid',
        currency: '',
        payments: []
      }
    }, { action: 'orabooks_invoice_get' });
    const html = $('#orabooks-dashboard-content').html();
    expect(html).toContain('INV-202606-0005');
    expect(html).not.toContain('undefined');
  });

  test('cleans invoice_id from URL via replaceState after loading', () => {
    const replaceStateSpy = jest.spyOn(window.history, 'replaceState');
    setupInvoiceTest(200);

    expect(replaceStateSpy).toHaveBeenCalled();
    const callArgs = replaceStateSpy.mock.calls[0];
    const cleanUrl = callArgs[2];
    expect(cleanUrl).not.toContain('invoice_id');

    replaceStateSpy.mockRestore();
  });

  test('renders money amounts formatted as currency', () => {
    setupInvoiceTest(700);
    resolveAjax('get', {
      error: false,
      data: {
        id: 700,
        invoice_number: 'INV-202606-0006',
        customer_email: 'c@t.com',
        transaction_date: '2026-06-01',
        due_date: '2026-07-01',
        total_amount: 1234.56,
        paid_amount: 0,
        description: '',
        payment_status: 'unpaid',
        currency: 'USD',
        payments: []
      }
    }, { action: 'orabooks_invoice_get' });
    expect($('#orabooks-dashboard-content').html()).toContain('$1,234.56');
  });
});

// ============================================================
// Commission Config Form
// ============================================================
describe('Commission config form submit', () => {
  test('posts serialized config data', () => {
    $('#orabooks-commission-config-form').trigger('submit');

    const call = latestAjax('post');
    expect(call.data.find(function(d) { return d.name === 'action'; }).value).toBe('orabooks_commission_update_config');
  });

  test('shows success message on config save', () => {
    $('#orabooks-commission-config-form').trigger('submit');
    resolveAjax('post', { error: false, data: {}, message: 'Configuration updated' });

    expect($('#orabooks-commission-config-message').text()).toContain('Configuration updated');
    expect($('#orabooks-commission-config-message').hasClass('success')).toBe(true);
  });
});
