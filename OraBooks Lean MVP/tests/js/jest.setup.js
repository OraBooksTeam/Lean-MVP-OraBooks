/**
 * Jest Setup for OraBooks JS Tests
 *
 * This file sets up the JSDOM test environment for testing admin.js and
 * frontend.js. The OraBooks JS files use jQuery's document.ready() and
 * make AJAX calls via $.get()/$.post() that need to be intercepted and
 * controlled in tests.
 *
 * ── Key Test Patterns ───────────────────────────────────────────────
 *
 * 1.  $.fn.ready fires synchronously
 *     jQuery schedules ready handlers with setTimeout(fn, 1) when
 *     document.readyState === 'complete'. Since jest.useFakeTimers()
 *     blocks that timeout, we override $.fn.ready to invoke the handler
 *     immediately instead.
 *
 * 2.  window.location mock
 *     JSDOM throws "Not implemented: navigation" on location.href
 *     assignment. We replace the inherited Location (which has
 *     non-configurable getters/setters on Window.prototype) with a
 *     plain data property on global that has a getter/setter for .href
 *     and stubs for .assign, .replace, .reload. Tests can read/write
 *     .href without triggering navigation errors.
 *
 * 3.  AJAX mock — $.get and $.post are intercepted
 *     Both shorthands are spied on via jest.spyOn. Instead of making
 *     real HTTP requests, they push an entry to ajaxResponses.get or
 *     ajaxResponses.post and return a jqXHR-like object with chainable
 *     .fail(), .always(), .done(), .then() stubs.
 *
 *     Helpers exposed globally:
 *
 *       resolveAjax(type, responseData, [action])
 *         Finds an AJAX entry by type ('get'|'post'), fires its
 *         callback with the supplied responseData, then fires any
 *         chained callbacks (done, then, always). If `action` is a
 *         string, it filters by data.action — otherwise takes the
 *         first entry (shift). Throws if no matching call is found.
 *
 *       latestAjax(type = 'post')
 *         Returns the last AJAX entry of the given type without
 *         consuming it. Use for checking request params.
 *
 *       clearAjax()
 *         Empties both queues. Call before triggering code that may
 *         make AJAX calls to start with a clean slate.
 *
 * 4.  Fake timers
 *     jest.useFakeTimers() is enabled globally. Tests that need to
 *     fire setTimeout callbacks must call jest.advanceTimersByTime().
 *     Be careful with jQuery animation methods like fadeOut() — they
 *     use setInterval internally and may hang under fake timers.
 *
 * 5.  HTMLFormElement.prototype.submit stubbed
 *     JSDOM doesn't implement form submission natively. The stub
 *     prevents "Not implemented" errors.
 *
 * 6.  localStorage mock
 *     Simple key-value store with jest.fn() methods.
 *
 * ── Writing Tests ───────────────────────────────────────────────────
 *
 *   beforeEach(() => {
 *     setupDom();
 *     clearAjax();           // empty any stale calls
 *     loadFileJs();          // runs via new Function — fires ready handler
 *   });
 *
 *   test('does something', () => {
 *     clearAjax();           // clear init calls from ready handler
 *     window.functionName(); // trigger the code under test
 *     resolveAjax('get', { error: false, data: [...] });
 *     expect(domElement).toContain('Expected output');
 *   });
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
// tests or code assign to window.location.href. We define a `location`
// property directly on the window INSTANCE to shadow the inherited
// Location object from Window.prototype. This avoids JSDOM's non-configurable
// Location.prototype getters/setters entirely.
const origLocation = global.window.location;
if (origLocation) {
  // Create a custom location object with mocked navigation methods
  const mockLocation = {
    _href: origLocation.href || 'https://example.com/dashboard/',
    protocol: 'https:',
    host: 'example.com',
    hostname: 'example.com',
    port: '',
    pathname: '/dashboard/',
    search: '',
    hash: '',
    origin: 'https://example.com',
    assign: jest.fn(),
    replace: jest.fn(),
    reload: jest.fn(),
    toString() { return this._href; },
    get href() { return this._href; },
    set href(v) { this._href = String(v); }
  };

  // Define `location` as a plain DATA property on the global scope.
  // In JSDOM, `location` is inherited from Window.prototype as a
  // non-configurable getter/setter. By defining it as an own data property
  // (value, not get/set), we completely replace the Location object with
  // our mock. This works because we're creating a new own property that
  // shadows the inherited one, without trying to reconfigure the inherited one.
  try {
    Object.defineProperty(global, 'location', {
      value: mockLocation,
      writable: true,
      configurable: true,
      enumerable: true
    });
  } catch (e) {
    // Fallback: location was non-configurable on this JSDOM version
    if (origLocation) {
      origLocation.assign = jest.fn();
      origLocation.replace = jest.fn();
      origLocation.reload = jest.fn();
    }
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

// Disable jQuery animations globally so fadeIn/fadeOut complete instantly
// under jest.useFakeTimers() — animations would never complete otherwise.
$.fx.off = true;

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

// Mock sessionStorage (used by OIDC flow)
const sessionStorageMock = (() => {
  let store = {};
  return {
    getItem: jest.fn((key) => store[key] || null),
    setItem: jest.fn((key, value) => { store[key] = String(value); }),
    removeItem: jest.fn((key) => { delete store[key]; }),
    clear: jest.fn(() => { store = {}; })
  };
})();
Object.defineProperty(global.window, 'sessionStorage', { value: sessionStorageMock });

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

jest.spyOn($, 'ajax').mockImplementation((options) => {
  const opts = options || {};
  const type = String(opts.type || 'GET').toLowerCase() === 'get' ? 'get' : 'post';
  return createMockJqXHR(opts.url, opts.data, opts.success || null, type);
});

// Helper to resolve the latest AJAX call.
// If action is provided as 3rd arg, finds and resolves the matching call
// by filtering on data.action (much more reliable than shift()).
global.resolveAjax = function(type, responseData, action) {
  if (typeof type !== 'string') {
    // Backwards compat: resolveAjax(responseData) — infer type from action
    action = responseData;
    responseData = type;
    type = 'post';
  }
  responseData = responseData || {};
  const calls = ajaxResponses[type];
  let call;
  if (action && typeof action === 'string') {
    var foundIdx = -1;
    for (var i = 0; i < calls.length; i++) {
      if (calls[i].data && calls[i].data.action === action) {
        foundIdx = i;
        break;
      }
    }
    if (foundIdx === -1) throw new Error('No ' + type + ' AJAX call with action=' + action);
    call = calls.splice(foundIdx, 1)[0];
  } else if (action && typeof action === 'object') {
    // Fallback: filter by key-value match
    var keys = Object.keys(action);
    var foundIdx = -1;
    for (var i = 0; i < calls.length; i++) {
      var match = true;
      for (var k = 0; k < keys.length; k++) {
        var key = keys[k];
        if (!calls[i].data || calls[i].data[key] !== action[key]) {
          match = false;
          break;
        }
      }
      if (match) {
        foundIdx = i;
        break;
      }
    }
    if (foundIdx === -1) throw new Error('No ' + type + ' AJAX call matching ' + JSON.stringify(action));
    call = calls.splice(foundIdx, 1)[0];
  } else {
    if (calls.length === 0) throw new Error('No ' + type + ' AJAX calls to resolve');
    call = calls.shift();
  }
  if (call.callback) call.callback(responseData);
  if (typeof call.doneCallback === 'function') call.doneCallback(responseData);
  if (typeof call.thenCallback === 'function') call.thenCallback(responseData);
  if (typeof call.alwaysCallback === 'function') call.alwaysCallback(responseData);
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
