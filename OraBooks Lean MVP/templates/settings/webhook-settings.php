<?php
/**
 * SL-303 Webhook Settings template.
 */
if (!defined('ABSPATH')) {
    exit;
}

$org_id = function_exists('orabooks_get_current_org_id') ? (int) orabooks_get_current_org_id(get_current_user_id()) : 0;
$urls = class_exists('OraBooks_AsyncQueue') ? implode("\n", OraBooks_AsyncQueue::get_webhook_urls($org_id)) : '';
?>
<div class="wrap orabooks-webhook-settings">
    <h1>Webhook Settings</h1>
    <p>Configure one webhook URL per line. Domain events are dispatched as webhook jobs.</p>
    <div class="notice notice-warning inline">
        <p>Localhost URLs are useful for testing only; hosted webhook services cannot call your local machine or private port.</p>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
        <input type="hidden" name="action" value="orabooks_webhook_settings_save" />
        <?php wp_nonce_field('orabooks_nonce', 'nonce'); ?>
        <textarea name="urls" rows="10" class="large-text code" placeholder="https://webhook.site/..."><?php echo esc_textarea($urls); ?></textarea>
        <p><button type="submit" class="button button-primary">Save Webhooks</button></p>
    </form>
</div>
