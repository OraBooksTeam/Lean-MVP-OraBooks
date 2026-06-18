<?php
/** @var string $initial_route */
$route = isset($initial_route) ? $initial_route : '/dashboard';
?>
<div class="orabooks-react-page">
    <div
        id="orabooks-app-root"
        class="orabooks-app-root"
        data-initial-route="<?php echo esc_attr($route); ?>"
    >
        <p class="orabooks-app-root-loading"><?php esc_html_e('Loading OraBooks…', 'orabooks'); ?></p>
    </div>
</div>
