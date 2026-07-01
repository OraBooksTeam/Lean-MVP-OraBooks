<?php
$token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
$sent = isset($_GET['sent']) ? sanitize_text_field(wp_unslash($_GET['sent'])) : '';
?>
<div class="orabooks-auth-shell">
    <div class="orabooks-form-container">
        <h2><?php esc_html_e('Verify Email', 'orabooks'); ?></h2>
        <p><?php esc_html_e('Confirm your email address to activate your account.', 'orabooks'); ?></p>
        <div id="orabooks-verify-email-message" class="orabooks-message"></div>
        <?php if (!$token && $sent === '1') : ?>
            <script>
              document.addEventListener('DOMContentLoaded', function () {
                var msg = document.getElementById('orabooks-verify-email-message');
                if (!msg) return;
                msg.className = 'orabooks-message success';
                msg.textContent = 'A verification link has been sent to your email. Check your inbox to continue.';
                msg.style.display = 'block';
              });
            </script>
        <?php endif; ?>
        <button type="button" id="orabooks-verify-email-btn" class="orabooks-btn orabooks-btn-primary" data-token="<?php echo esc_attr($token); ?>">
            <?php esc_html_e('Verify Email', 'orabooks'); ?>
        </button>
        <p class="orabooks-auth-links">
            <a href="<?php echo esc_url(orabooks_get_network_login_url('login')); ?>"><?php esc_html_e('Go to login', 'orabooks'); ?></a>
        </p>
    </div>
</div>
<script>
(function () {
  var btn = document.getElementById('orabooks-verify-email-btn');
  var msg = document.getElementById('orabooks-verify-email-message');
  if (!btn || !window.orabooks_ajax) return;
  btn.addEventListener('click', function () {
    btn.disabled = true;
    btn.textContent = 'Verifying...';
    var body = new URLSearchParams();
    body.set('action', 'orabooks_verify_email_token');
    body.set('_ajax_nonce', orabooks_ajax.nonce || '');
    body.set('token', btn.getAttribute('data-token') || '');
    fetch(orabooks_ajax.ajax_url, { method: 'POST', body: body })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        msg.className = 'orabooks-message ' + (res.error ? 'error' : 'success');
        msg.textContent = res.message || (res.error ? 'Verification failed.' : 'Email verified. You can log in now.');
        msg.style.display = 'block';
      })
      .catch(function () {
        msg.className = 'orabooks-message error';
        msg.textContent = 'Verification failed. Please try again.';
        msg.style.display = 'block';
      })
      .finally(function () {
        btn.disabled = false;
        btn.textContent = 'Verify Email';
      });
  });
})();
</script>
