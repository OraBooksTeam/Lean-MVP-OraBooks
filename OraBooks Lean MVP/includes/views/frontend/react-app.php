<?php
/** @var string $initial_route */
/** @var bool $require_login */
$route = isset($initial_route) ? $initial_route : '/dashboard';
$needs_auth = !isset($require_login) || $require_login;
?>
<div class="orabooks-react-page">
    <div
        id="orabooks-app-root"
        class="orabooks-app-root"
        data-initial-route="<?php echo esc_attr($route); ?>"
        data-require-auth="<?php echo $needs_auth ? '1' : '0'; ?>"
    >
        <p class="orabooks-app-root-loading"><?php esc_html_e('Loading OraBooks…', 'orabooks'); ?></p>
    </div>
</div>
