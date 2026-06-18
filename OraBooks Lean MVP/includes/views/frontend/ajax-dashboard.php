<?php
/** @var string $title */
/** @var string $ajax_action */
/** @var string $description */
?>
<div class="orabooks-ajax-dashboard" data-ajax-action="<?php echo esc_attr($ajax_action); ?>">
    <h2><?php echo esc_html($title); ?></h2>
    <?php if (!empty($description)) : ?>
        <p class="description"><?php echo esc_html($description); ?></p>
    <?php endif; ?>
    <div class="orabooks-ajax-dashboard-content">
        <p class="orabooks-loading"><?php esc_html_e('Loading...', 'orabooks'); ?></p>
    </div>
</div>
