<?php
/** @var string $initial_route */
/** @var bool $require_login */
$route = isset($initial_route) ? $initial_route : '/dashboard';
$needs_auth = !isset($require_login) || $require_login;
$ajax_config = class_exists('OraBooks_Assets') ? OraBooks_Assets::get_ajax_config('frontend') : [];
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
<script>
window.orabooks_ajax = window.orabooks_ajax || <?php echo wp_json_encode($ajax_config); ?>;
(function () {
    function recoverOraBooksMount() {
        var root = document.getElementById('orabooks-app-root');
        if (!root || root.classList.contains('is-mounted') || window.orabooksReactMounted) {
            return;
        }
        if (typeof window.orabooksBootFrontend === 'function') {
            window.orabooksBootFrontend();
            if (root.classList.contains('is-mounted') || window.orabooksReactMounted) {
                return;
            }
        }
        if (document.querySelector('script[data-orabooks-react-fallback="1"]')) {
            return;
        }
        var fallbackSrc = <?php echo wp_json_encode(class_exists('OraBooks_Assets') ? OraBooks_Assets::react_bundle_url('frontend.js') : ''); ?>;
        if (!fallbackSrc) {
            return;
        }
        var script = document.createElement('script');
        script.src = fallbackSrc;
        script.defer = true;
        script.dataset.orabooksReactFallback = '1';
        document.body.appendChild(script);
    }
    window.addEventListener('load', function () {
        window.setTimeout(recoverOraBooksMount, 400);
    });
})();
</script>
