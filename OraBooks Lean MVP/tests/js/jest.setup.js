/**
 * Jest Setup for OraBooks JS Tests
 *
 * Provides:
 * - window.orabooks_ajax mock
 * - Global alert/confirm mocks
 * - Fake timers for setTimeout tests
 * - localStorage mock
 * - jQuery loaded from node_modules
 * - Utility to load JS file content
 */

const $ = require('jquery');
const fs = require('fs');
const path = require('path');

// --- Global WordPress-like objects ---
global.window = global.window || {};
global.document = global.document || {};

// Provide orabooks_ajax global
global.window.orabooks_ajax = {
  ajax_url: 'https://example.com/wp-admin/admin-ajax.php',
  nonce: 'test-nonce'
};

// Provide orabooks_ajax also on frontend (might have current_user_id)
global.window.orabooks_ajax.current_user_id = 1;

// Mock alert and confirm
global.window.alert = jest.fn();
global.window.confirm = jest.fn(() => true);

// Fake timers
jest.useFakeTimers();

// Mock localStorage
const localStorageMock = (() => {
  let store = {};
  return {
    getItem: jest.fn((key) => store[key] || null),
    setItem: jest.fn((key, value) => { store[key] = String(value); }),
    removeItem: jest.fn((key) => { delete store[key]; }),
    clear: jest.fn(() => { store = {}; })
  };
})();
Object.defineProperty(global.window, 'localStorage', { value: localStorageMock });

// Expose jQuery as global
global.window.jQuery = $;
global.window.$ = $;

// Track AJAX responses for test assertions
const ajaxResponses = {
  get: [],
  post: []
};
global.__ajaxResponses = ajaxResponses;

// Spy on $.get and $.post — return a jqXHR-like object
jest.spyOn($, 'get').mockImplementation((url, data, callback) => {
  const type = 'get';
  ajaxResponses.get.push({ url, data, callback });
  // Store for later resolution in tests
  return { url, data, callback, type };
});

jest.spyOn($, 'post').mockImplementation((url, data, callback) => {
  const type = 'post';
  ajaxResponses.post.push({ url, data, callback });
  return { url, data, callback, type };
});

// Helper to resolve the latest AJAX call
global.resolveAjax = function(type = 'post', responseData = {}, responseMessage = '') {
  const calls = ajaxResponses[type];
  if (calls.length === 0) throw new Error(`No ${type} AJAX calls to resolve`);
  const call = calls.shift();
  if (call.callback) {
    call.callback(responseData);
  }
};

// Helper to reject (fail) the latest AJAX call
global.failAjax = function(type = 'post') {
  const calls = ajaxResponses[type];
  if (calls.length === 0) throw new Error(`No ${type} AJAX calls to fail`);
  const call = calls.shift();
  if (call.fail) {
    call.fail();
  }
};

// Helper to get the latest AJAX call data without resolving
global.latestAjax = function(type = 'post') {
  const calls = ajaxResponses[type];
  return calls.length > 0 ? calls[calls.length - 1] : null;
};

// Helper to clear AJAX call queue
global.clearAjax = function() {
  ajaxResponses.get.length = 0;
  ajaxResponses.post.length = 0;
};

// Helper to load a JS file as text and execute it in the jQuery ready context
global.loadScript = function(relativePath) {
  const fullPath = path.resolve(__dirname, relativePath);
  const code = fs.readFileSync(fullPath, 'utf8');
  // Execute in a context where $ is available
  const executeInContext = new Function('$', 'jQuery', code);
  executeInContext($, $);
  return code;
};

// Reset between tests
beforeEach(() => {
  clearAjax();
  window.alert.mockClear();
  window.confirm.mockClear();
  jest.clearAllTimers();
  localStorageMock.clear();
});
