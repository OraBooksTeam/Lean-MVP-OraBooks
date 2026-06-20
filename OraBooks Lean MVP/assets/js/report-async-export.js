(function () {
  function getAjaxUrl() {
    return (window.orabooks_ajax && window.orabooks_ajax.ajax_url) || window.ajaxurl || '/wp-admin/admin-ajax.php';
  }

  function collectPayload(button) {
    var payload = new FormData();
    payload.append('action', 'orabooks_report_async_export');
    payload.append('report_type', button.getAttribute('data-report-type') || '');
    ['date_from', 'date_to', 'start_date', 'end_date', 'account_id'].forEach(function (name) {
      var selector = button.getAttribute('data-' + name.replace('_', '-')) || ('[name="' + name + '"]');
      var field = document.querySelector(selector);
      if (field && field.value) {
        payload.append(name, field.value);
      }
    });
    if (window.orabooks_ajax && window.orabooks_ajax.nonce) {
      payload.append('nonce', window.orabooks_ajax.nonce);
    }
    return payload;
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-orabooks-async-export]');
    if (!button) return;
    event.preventDefault();
    button.disabled = true;
    var original = button.textContent;
    button.textContent = 'Queueing...';

    fetch(getAjaxUrl(), {
      method: 'POST',
      credentials: 'same-origin',
      body: collectPayload(button),
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (json) {
        if (!json || json.success === false) {
          throw new Error((json && json.data && json.data.message) || 'Failed to queue export.');
        }
        button.textContent = 'Queued';
      })
      .catch(function (error) {
        window.alert(error.message || 'Failed to queue export.');
        button.textContent = original;
      })
      .finally(function () {
        button.disabled = false;
      });
  });
})();
