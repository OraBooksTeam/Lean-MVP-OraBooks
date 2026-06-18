<?php
/**
 * Shared React mount for OraBooks wp-admin pages.
 *
 * @var string $orabooks_admin_route Hash route e.g. /admin/organizations
 */
if (!defined('ABSPATH')) {
    exit;
}

$route = isset($orabooks_admin_route) ? $orabooks_admin_route : '/admin/dashboard';
?>
<div class="wrap orabooks-admin orabooks-admin-react">
    <div
        id="orabooks-admin-root"
        class="orabooks-admin-root"
        data-admin-route="<?php echo esc_attr($route); ?>"
    >
        <p class="orabooks-admin-root-loading"><?php esc_html_e('Loading OraBooks…', 'orabooks'); ?></p>
    </div>
</div>
