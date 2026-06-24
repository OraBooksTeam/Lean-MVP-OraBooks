<?php
/**
 * Owner-only SL-302 dead-letter replay UI.
 *
 * @var array $health
 * @var array $dead_letters
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap orabooks-event-dead-letter">
    <h1><?php esc_html_e('Event Dead Letters', 'orabooks'); ?></h1>
    <p><?php esc_html_e('Replay or discard failed SL-302 domain events after reviewing the error.', 'orabooks'); ?></p>

    <div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0;">
        <div class="card"><strong><?php esc_html_e('Status', 'orabooks'); ?></strong><br><?php echo esc_html(strtoupper($health['status'] ?? 'healthy')); ?></div>
        <div class="card"><strong><?php esc_html_e('Pending', 'orabooks'); ?></strong><br><?php echo esc_html((string) ($health['pending'] ?? 0)); ?></div>
        <div class="card"><strong><?php esc_html_e('Sent', 'orabooks'); ?></strong><br><?php echo esc_html((string) ($health['sent'] ?? 0)); ?></div>
        <div class="card"><strong><?php esc_html_e('Dead Letter', 'orabooks'); ?></strong><br><?php echo esc_html((string) ($health['dead_letter'] ?? 0)); ?></div>
    </div>

    <p>
        <button class="button button-primary" data-event-action="poll"><?php esc_html_e('Poll Now', 'orabooks'); ?></button>
        <button class="button" data-event-action="replay-all"><?php esc_html_e('Replay All', 'orabooks'); ?></button>
    </p>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('ID', 'orabooks'); ?></th>
                <th><?php esc_html_e('Event', 'orabooks'); ?></th>
                <th><?php esc_html_e('Aggregate', 'orabooks'); ?></th>
                <th><?php esc_html_e('Retries', 'orabooks'); ?></th>
                <th><?php esc_html_e('Error', 'orabooks'); ?></th>
                <th><?php esc_html_e('Created', 'orabooks'); ?></th>
                <th><?php esc_html_e('Actions', 'orabooks'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($dead_letters)) : ?>
                <tr>
                    <td colspan="7"><?php esc_html_e('No open dead-letter events.', 'orabooks'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($dead_letters as $dead) : ?>
                    <tr>
                        <td><?php echo esc_html((string) $dead->id); ?></td>
                        <td><code><?php echo esc_html($dead->event_type); ?></code></td>
                        <td><?php echo esc_html((string) $dead->aggregate_id); ?></td>
                        <td><?php echo esc_html((string) $dead->retry_count); ?></td>
                        <td><?php echo esc_html(wp_trim_words((string) $dead->error_message, 18)); ?></td>
                        <td><?php echo esc_html((string) $dead->created_at); ?></td>
                        <td>
                            <button class="button button-small" data-event-action="replay" data-id="<?php echo esc_attr((string) $dead->id); ?>"><?php esc_html_e('Replay', 'orabooks'); ?></button>
                            <button class="button button-small" data-event-action="discard" data-id="<?php echo esc_attr((string) $dead->id); ?>"><?php esc_html_e('Discard', 'orabooks'); ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
(function () {
  const ajaxUrl = window.ajaxurl || '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
  const post = async (action, extra) => {
    const body = new URLSearchParams({ action, ...(extra || {}) });
    const res = await fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body });
    return res.json();
  };

  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-event-action]');
    if (!button) return;
    event.preventDefault();
    const action = button.getAttribute('data-event-action');
    button.disabled = true;
    try {
      if (action === 'poll') await post('orabooks_eventbus_poll_now');
      if (action === 'replay-all') await post('orabooks_eventbus_replay_all');
      if (action === 'replay') await post('orabooks_eventbus_replay', { dead_letter_id: button.getAttribute('data-id') });
      if (action === 'discard') await post('orabooks_eventbus_discard', { dead_letter_id: button.getAttribute('data-id') });
      window.location.reload();
    } catch (error) {
      window.alert('Event action failed. Please try again.');
      button.disabled = false;
    }
  });
})();
</script>
