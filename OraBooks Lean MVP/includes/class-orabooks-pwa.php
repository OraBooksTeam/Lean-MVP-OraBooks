<?php
/**
 * OraBooks Progressive Web App ( mobile / offline receipt queue)
 */

if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Pwa {

 public static function init() {
 add_action('wp_head', [__CLASS__, 'output_head_tags'], 5);
 add_filter('orabooks_ajax_config', [__CLASS__, 'extend_ajax_config']);
 }

 public static function is_enabled() {
 return apply_filters('orabooks_pwa_enabled', true);
 }

 public static function asset_url($file) {
 return ORABOOKS_PLUGIN_URL. 'assets/pwa/'. ltrim($file, '/');
 }

 public static function get_manifest() {
 $start = home_url('/dashboard/');
 $scope = home_url('/');

 return [
 'name' => 'OraBooks',
 'short_name' => 'OraBooks',
 'description' => 'Multi-tenant accounting — receipts, expenses, and approvals on the go.',
 'start_url' => $start,
 'scope' => $scope,
 'display' => 'standalone',
 'orientation' => 'portrait-primary',
 'background_color' => '#f4f8fc',
 'theme_color' => '#1A69B4',
 'categories' => ['finance', 'business', 'productivity'],
 'icons' => self::get_manifest_icons,
 'shortcuts' => [
 [
 'name' => 'Expenses',
 'short_name' => 'Expenses',
 'description' => 'Scan or upload a receipt',
 'url' => home_url('/expenses/'),
 ],
 ],
 ];
 }

 public static function get_manifest_icons() {
 $candidates = [
 [
 'file' => 'icons/icon-192.png',
 'sizes' => '192x192',
 'type' => 'image/png',
 'purpose' => 'any',
 ],
 [
 'file' => 'icons/icon-512.png',
 'sizes' => '512x512',
 'type' => 'image/png',
 'purpose' => 'any',
 ],
 [
 'file' => 'icons/icon-512.png',
 'sizes' => '512x512',
 'type' => 'image/png',
 'purpose' => 'maskable',
 ],
 [
 'file' => 'icons/icon.svg',
 'sizes' => 'any',
 'type' => 'image/svg+xml',
 'purpose' => 'any',
 ],
 ];

 $icons = [];
 foreach ($candidates as $candidate) {
 if (!file_exists(ORABOOKS_PLUGIN_DIR. 'assets/pwa/'. $candidate['file'])) {
 continue;
 }

 $icons[] = [
 'src' => self::asset_url($candidate['file']),
 'sizes' => $candidate['sizes'],
 'type' => $candidate['type'],
 'purpose' => $candidate['purpose'],
 ];
 }

 return $icons;
 }

 public static function service_worker_url() {
 return rest_url('api/pwa/service-worker');
 }

 public static function service_worker_scope() {
 return home_url('/');
 }

 public static function extend_ajax_config($config) {
 if (!self::is_enabled) {
 return $config;
 }

 $config['pwa'] = [
 'enabled' => true,
 'manifest_url' => rest_url('api/pwa/manifest'),
 'service_worker_url' => self::service_worker_url,
 'service_worker_scope' => self::service_worker_scope,
 'offline_queue' => true,
 ];

 return $config;
 }

 public static function output_head_tags() {
 if (!self::is_enabled || !is_singular('page')) {
 return;
 }

 $post = get_post;
 if (!$post || strpos($post->post_content, '[orabooks_') === false) {
 return;
 }

 $manifest = esc_url(rest_url('api/pwa/manifest'));
 $theme = esc_attr('#1A69B4');
 $apple_icon = esc_url(self::asset_url('icons/icon-192.png'));
 if (!file_exists(ORABOOKS_PLUGIN_DIR. 'assets/pwa/icons/icon-192.png')) {
 $apple_icon = esc_url(self::asset_url('icons/icon.svg'));
 }

 echo '<link rel="manifest" href="'. $manifest. '" />'. "\n";
 echo '<link rel="apple-touch-icon" href="'. $apple_icon. '" />'. "\n";
 echo '<meta name="theme-color" content="'. $theme. '" />'. "\n";
 echo '<meta name="mobile-web-app-capable" content="yes" />'. "\n";
 echo '<meta name="apple-mobile-web-app-capable" content="yes" />'. "\n";
 echo '<meta name="apple-mobile-web-app-status-bar-style" content="default" />'. "\n";
 echo '<meta name="apple-mobile-web-app-title" content="OraBooks" />'. "\n";
 }

 public static function rest_manifest() {
 $response = rest_ensure_response(self::get_manifest);
 $response->header('Cache-Control', 'public, max-age=3600');

 return $response;
 }

 public static function rest_service_worker() {
 $path = ORABOOKS_PLUGIN_DIR. 'assets/pwa/service-worker.js';
 if (!file_exists($path)) {
 return new WP_Error('orabooks_pwa_sw_missing', 'Service worker not found.', ['status' => 404]);
 }

 $body = (string) file_get_contents($path);
 $response = new WP_REST_Response($body, 200);
 $response->header('Content-Type', 'application/javascript; charset=utf-8');
 $response->header('Service-Worker-Allowed', '/');
 $response->header('Cache-Control', 'no-cache');

 return $response;
 }
}
