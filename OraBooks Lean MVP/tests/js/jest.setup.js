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

// Set readyState to 'complete' so jQuery ready handlers fire immediately
global.document = global.document || {};
Object.defineProperty(global.document, 'readyState', { value: 'complete', writable: false });

const $ = require('jquery');

// --- Global WordPress-like objects ---
global.window = global.window || {};

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

// --- Mock window.location to prevent JSDOM navigation errors ---
// JSDOM throws "Not implemented: navigation (except hash changes)" when
// tests or code assign to window.location.href. We shadow the prototype's
// getter/setter by defining `href` as a writable data property directly
// on the existing Location instance.
const origLocation = global.window.location;
if (origLocation) {
  origLocation.assign = jest.fn();
  origLocation.replace = jest.fn();
  origLocation.reload = jest.fn();
  // Override Location.prototype href getter/setter unconditionally to prevent
  // JSDOM navigation errors. JSDOM's Location uses a prototype getter/setter
  // that can't be shadowed by instance data properties.
  try {
    const locProto = Object.getPrototypeOf(origLocation);
    // Set an initial _href so the getter has a default
    origLocation._href = 'https://example.com/dashboard/';
    Object.defineProperty(locProto, 'href', {
      get() { return this._href || 'https://example.com/dashboard/'; },
      set(v) { this._href = String(v); },
      configurable: true
    });
  } catch (e) {
    // Some JSDOM versions may block prototype manipulation — ignore
  }
}

// --- Stub HTMLFormElement.prototype.submit ---
// JSDOM does not implement form submission. Stub to prevent
// "Error: Not implemented: HTMLFormElement.prototype.submit"
if (typeof HTMLFormElement !== 'undefined') {
  HTMLFormElement.prototype.submit = jest.fn();
}

// --- Override jQuery ready to fire synchronously ---
// jQuery schedules ready handlers via setTimeout(fn,1) when
// document.readyState === 'complete'. With jest.useFakeTimers(),
// that timeout would never fire. Override to call immediately.
if ($.fn && $.fn.ready) {
  $.fn.ready = function (fn) {
    if (typeof fn === 'function') {
      fn.call(document, $);
    }
    return this;
  };
}

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
global.ajaxResponses = ajaxResponses;
global.__ajaxResponses = ajaxResponses;

// ---- jqXHR-like mock for chaining support ----
// Some code in frontend.js/admin.js chains `.fail()` and `.always()`
// on $.get / $.post return values. Provide stub methods.
function createMockJqXHR(url, data, callback, type) {
  const entry = { url, data, callback };
  ajaxResponses[type].push(entry);
  return {
    url, data, callback, type,
    fail: jest.fn(function (fn) {
      if (typeof fn === 'function') {
        entry.failCallback = fn;
      }
      return this;
    }),
    always: jest.fn(function (fn) {
      if (typeof fn === 'function') {
        entry.alwaysCallback = fn;
      }
      return this;
    }),
    done: jest.fn(function (fn) {
      if (typeof fn === 'function') {
        entry.doneCallback = fn;
      }
      return this;
    }),
    then: jest.fn(function (fn) {
      if (typeof fn === 'function') {
        entry.thenCallback = fn;
      }
      return this;
    })
  };
}

// Spy on $.get and $.post — return a jqXHR-like object with chaining support
jest.spyOn($, 'get').mockImplementation((url, data, callback) => {
  const type = 'get';
  return createMockJqXHR(url, data, callback, type);
});

jest.spyOn($, 'post').mockImplementation((url, data, callback) => {
  const type = 'post';
  return createMockJqXHR(url, data, callback, type);
});

// Helper to resolve the latest AJAX call
global.resolveAjax = function(type = 'post', responseData = {}, responseMessage = '') {
  const calls = ajaxResponses[type];
  if (calls.length === 0) throw new Error(`No ${type} AJAX calls to resolve`);
  const call = calls.shift();
  if (call.callback) {
    call.callback(responseData);
  }
  // Also fire done/then callbacks if they were registered via chaining
  if (typeof call.doneCallback === 'function') {
    call.doneCallback(responseData);
  }
  if (typeof call.thenCallback === 'function') {
    call.thenCallback(responseData);
  }
  // Always invoke always callback
  if (typeof call.alwaysCallback === 'function') {
    call.alwaysCallback(responseData);
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



// Reset between tests — moved to each test file's beforeEach()
// because setupFiles run before the test framework is installed.
// Each test file should call clearAjax, mockClear, clearAllTimers, etc.
