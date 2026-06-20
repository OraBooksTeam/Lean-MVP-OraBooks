const $ = require('jquery');
const { TextDecoder, TextEncoder } = require('util');

global.TextDecoder = global.TextDecoder || TextDecoder;
global.TextEncoder = global.TextEncoder || TextEncoder;

const ajaxResponses = {
  get: [],
  post: [],
};

$.fn.ready = function ready(callback) {
  callback.call(document, $);
  return this;
};
$.fx.off = true;

function normalizeMethod(method) {
  return String(method || 'get').toLowerCase();
}

function actionFromData(data) {
  if (Array.isArray(data)) {
    const entry = data.find((item) => item && item.name === 'action');
    return entry ? entry.value : undefined;
  }

  return data && data.action;
}

function dataMatches(data, filter) {
  if (!filter) {
    return true;
  }

  if (typeof filter === 'string') {
    return actionFromData(data) === filter;
  }

  if (typeof filter === 'object') {
    return Object.entries(filter).every(([key, expected]) => {
      if (Array.isArray(data)) {
        const entry = data.find((item) => item && item.name === key);
        return entry && entry.value === expected;
      }

      return data && data[key] === expected;
    });
  }

  return true;
}

function createJqXhr(call) {
  return {
    done(callback) {
      call.doneCallback = callback;
      call.doneHandlers.push(callback);
      return this;
    },
    fail(callback) {
      call.failCallback = callback;
      call.failHandlers.push(callback);
      return this;
    },
    always(callback) {
      call.alwaysCallback = callback;
      call.alwaysHandlers.push(callback);
      return this;
    },
    then(callback) {
      call.thenCallback = callback;
      call.doneHandlers.push(callback);
      return this;
    },
  };
}

function queueAjax(method, url, data, success, options = {}) {
  const normalizedMethod = normalizeMethod(method);
  const call = {
    method: normalizedMethod,
    url,
    data: data || {},
    callback: success,
    success,
    options,
    doneHandlers: [],
    failHandlers: [],
    alwaysHandlers: [],
  };

  ajaxResponses[normalizedMethod].push(call);
  return createJqXhr(call);
}

$.get = jest.fn((url, data, success) => queueAjax('get', url, data, success));
$.post = jest.fn((url, data, success) => queueAjax('post', url, data, success));
$.ajax = jest.fn((options) => {
  const method = normalizeMethod(options.type || options.method);
  return queueAjax(method, options.url, options.data, options.success, options);
});

global.ajaxResponses = ajaxResponses;
global.clearAjax = function clearAjax() {
  ajaxResponses.get.length = 0;
  ajaxResponses.post.length = 0;
  $.get.mockClear();
  $.post.mockClear();
  $.ajax.mockClear();
};

global.latestAjax = function latestAjax(method) {
  const calls = ajaxResponses[normalizeMethod(method)];
  return calls[calls.length - 1] || null;
};

global.resolveAjax = function resolveAjax(method, response, filter) {
  const calls = ajaxResponses[normalizeMethod(method)];
  const index = calls.findIndex((call) => dataMatches(call.data, filter));

  if (index === -1) {
    throw new Error(`No ${method} AJAX call found for filter ${JSON.stringify(filter)}`);
  }

  const [call] = calls.splice(index, 1);

  if (typeof call.success === 'function') {
    call.success(response);
  }

  if (typeof call.options.success === 'function') {
    call.options.success(response);
  }

  call.doneHandlers.forEach((callback) => callback(response));
  call.alwaysHandlers.forEach((callback) => callback(response));

  return call;
};

beforeEach(() => {
  jest.useFakeTimers();

  window.alert = jest.fn();
  window.confirm = jest.fn(() => true);

  window.orabooks_ajax = {
    ajax_url: '/wp-admin/admin-ajax.php',
    current_user_id: 123,
    nonce: 'test-nonce',
  };
  global.orabooks_ajax = window.orabooks_ajax;

  const createStorageMock = () => {
    const store = {};

    return {
      getItem: jest.fn((key) => (Object.prototype.hasOwnProperty.call(store, key) ? store[key] : null)),
      setItem: jest.fn((key, value) => {
        store[key] = String(value);
      }),
      removeItem: jest.fn((key) => {
        delete store[key];
      }),
      clear: jest.fn(() => {
        Object.keys(store).forEach((key) => delete store[key]);
      }),
    };
  };

  Object.defineProperty(window, 'localStorage', {
    configurable: true,
    value: createStorageMock(),
  });

  Object.defineProperty(window, 'sessionStorage', {
    configurable: true,
    value: createStorageMock(),
  });

  Object.defineProperty(window.navigator, 'clipboard', {
    configurable: true,
    value: {
      writeText: jest.fn(() => Promise.resolve()),
    },
  });

  clearAjax();
});

Object.defineProperty(HTMLFormElement.prototype, 'submit', {
  configurable: true,
  value: jest.fn(),
});

afterEach(() => {
  jest.runOnlyPendingTimers();
  jest.useRealTimers();
});
