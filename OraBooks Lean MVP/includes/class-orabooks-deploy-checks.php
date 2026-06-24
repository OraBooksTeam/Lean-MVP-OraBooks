<?php
/**
 * Post-deploy verification checks for production smoke testing.
 */

if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_DeployChecks {
 /**
 * MVP cron hooks verified after deploy (partner activity, async queue, customer active status).
 *
 * @return array<string, string> hook => recurrence
 */
 public static function mvp_cron_jobs() {
 return [
 'orabooks_partner_activity_check' => 'daily',
 'orabooks_async_queue_process' => 'every_minute',
 'orabooks_async_queue_archive' => 'daily',
 'orabooks_daily_active_status_refresh' => 'daily',
 'orabooks_team_cleanup_expired_invites' => 'daily',
 'orabooks_approval_expire_stale' => 'hourly',
 'orabooks_approval_escalate_overdue' => 'hourly',
 'orabooks_approval_expiry_reminders' => 'hourly',
 ];
 }

 /**
 * Schedule missing MVP crons (idempotent). Returns hooks that were newly scheduled.
 *
 * @return string[]
 */
 public static function ensure_mvp_cron_schedules() {
 $repaired = [];

 foreach (self::mvp_cron_jobs as $hook => $recurrence) {
 if (wp_next_scheduled($hook)) {
 continue;
 }

 wp_schedule_event(time, $recurrence, $hook);
 $repaired[] = $hook;
 }

 return $repaired;
 }

 /**
 * @return array{ok:bool,checks:array<int,array{id:string,label:string,ok:bool,detail:string}>,timestamp:string,environment:array<string,mixed>}
 */
 public static function run() {
 $checks = [];
 $ok = true;

 $add_check = function($id, $label, $passed, $detail = '') use (&$checks, &$ok) {
 $passed = (bool) $passed;
 if (!$passed) {
 $ok = false;
 }
 $checks[] = [
 'id' => (string) $id,
 'label' => (string) $label,
 'ok' => $passed,
 'detail' => (string) $detail,
 ];
 };

 $secrets_ok = true;
 $secrets_detail = '';

 if (class_exists('OraBooks_Secrets')) {
 $status = OraBooks_Secrets::get_status;
 $secrets_ok = !empty($status['jwt_secret_configured'])
 && !empty($status['encryption_key_configured']);

 if (OraBooks_Secrets::requires_tls && empty($status['https_active'])) {
 $secrets_ok = false;
 $secrets_detail = 'HTTPS required in production';
 }

 $tls = $status['tls'] ?? [];
 if (!empty($tls['expired'])) {
 $secrets_ok = false;
 $secrets_detail = 'TLS certificate expired for '. ($tls['host'] ?? 'site host');
 } elseif (!empty($tls['expiring_soon'])) {
 $secrets_detail = 'TLS certificate expiring in '. ($tls['days_remaining'] ?? '?'). ' days';
 }

 if (empty($status['bootstrap_ready'])) {
 $secrets_ok = false;
 $secrets_detail = $secrets_detail ?: 'Secrets bootstrap failed — see admin notice';
 }
 } else {
 $legacy_jwt = get_option('orabooks_jwt_secret');
 $secrets_ok = !empty($legacy_jwt);
 }

 $add_check('jwt_secret', 'JWT secret configured', $secrets_ok, $secrets_detail);

 $react_view = class_exists('OraBooks_Views') && OraBooks_Views::exists('frontend/react-app');
 $add_check(
 'react_app_view',
 'React SPA view template present',
 $react_view,
 $react_view ? 'includes/views/frontend/react-app.php': 'missing includes/views/frontend/react-app.php'
 );

 $react_bundle = defined('ORABOOKS_PLUGIN_DIR')
 && file_exists(ORABOOKS_PLUGIN_DIR. 'assets/react/frontend.js');
 $add_check(
 'react_frontend_bundle',
 'React frontend bundle built',
 $react_bundle,
 $react_bundle ? 'assets/react/frontend.js': 'run npm run build in orabooks-ui'
 );

 $add_check(
 'encryption_key',
 'Encryption key configured',
 class_exists('OraBooks_Secrets')
 ? !empty(OraBooks_Secrets::get_status['encryption_key_configured'])
: true,
 $secrets_detail
 );

 if (class_exists('OraBooks_Secrets')) {
 $db_tls = OraBooks_Secrets::check_database_tls;
 $db_tls_ok = !empty($db_tls['ok']) || !empty($db_tls['skipped']);
 $db_tls_detail = '';
 if (!$db_tls_ok) {
 $db_tls_detail = 'Enable MYSQL_CLIENT_FLAGS|MYSQL_SSL_CA|DB_SSL or ORABOOKS_DB_SSL=1 — see docs/-secret-rotation-runbook.md';
 } elseif (!empty($db_tls['indicators'])) {
 $db_tls_detail = 'indicators: '. implode(', ', (array) $db_tls['indicators']);
 }
 $add_check('database_tls', 'Database connection uses TLS (production)', $db_tls_ok, $db_tls_detail);

 $tls_status = OraBooks_Secrets::get_status['tls'] ?? [];
 $tls_provision_ok = true;
 $tls_provision_detail = 'Configure Let\'s Encrypt or enterprise CA at the load balancer — see docs/-secret-rotation-runbook.md';
 if (OraBooks_Secrets::requires_tls && !empty($tls_status['skipped']) && empty($tls_status['ok'])) {
 $tls_provision_ok = false;
 } elseif (!empty($tls_status['expired'])) {
 $tls_provision_ok = false;
 $tls_provision_detail = 'Certificate expired for '. ($tls_status['host'] ?? 'site host');
 } elseif (!empty($tls_status['expiring_soon'])) {
 $tls_provision_detail = 'Renew before expiry ('. ($tls_status['days_remaining'] ?? '?'). ' days left)';
 } elseif (!empty($tls_status['expires_at'])) {
 $tls_provision_detail = 'Valid until '. $tls_status['expires_at'];
 }
 $add_check('tls_certificate', 'TLS certificate provisioned', $tls_provision_ok, $tls_provision_detail);
 }

 $db_version = get_option('orabooks_db_version');
 $expected_db_version = defined('ORABOOKS_DB_VERSION') ? ORABOOKS_DB_VERSION: '1.0.1';
 $add_check(
 'db_version',
 'Database schema version',
 $db_version === $expected_db_version,
 'expected '. $expected_db_version. ', got '. ($db_version ?: 'none')
 );

 $table_prefix = '';
 $run_table_checks = function() use (&$table_prefix, $add_check) {
 global $wpdb;

 $table_prefix = function_exists('orabooks_get_table_prefix')
 ? orabooks_get_table_prefix
: $wpdb->prefix;
 $add_check('table_prefix', 'Shared table prefix resolved', $table_prefix !== '', $table_prefix);

 $required_tables = [
 'users',
 'organizations',
 'accounts',
 'customers',
 'invoices',
 'payments',
 'async_jobs',
 'partner_codes',
 'partner_attributions',
 'audit_logs',
 'refresh_tokens',
 'state_machine_transitions',
 'journal_approval_history',
 'approval_policies',
 'approval_delegations',
 'journals',
 ];

 foreach ($required_tables as $name) {
 $table = OraBooks_Database::table($name);
 $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
 $add_check('table_'. $name, 'Table exists: '. $name, $exists, $table);
 }

 $invoices_table = OraBooks_Database::table('invoices');
 $columns = $wpdb->get_col("SHOW COLUMNS FROM {$invoices_table}", 0);
 $has_sl021_columns = is_array($columns)
 && in_array('payment_status', $columns, true)
 && in_array('paid_amount', $columns, true);
 $add_check(
 'invoices_schema',
 'Invoices columns present',
 $has_sl021_columns,
 is_array($columns) ? implode(', ', $columns): 'columns unavailable'
 );
 };

 if (function_exists('orabooks_with_data_blog')) {
 orabooks_with_data_blog($run_table_checks);
 } else {
 $run_table_checks;
 }

 $cron_hooks = array_keys(self::mvp_cron_jobs);
 foreach ($cron_hooks as $hook) {
 $next = wp_next_scheduled($hook);
 $add_check(
 'cron_'. $hook,
 'Cron scheduled: '. $hook,
 $next !== false,
 $next ? gmdate('c', $next): 'not scheduled'
 );
 }

 $partner_handler = class_exists('OraBooks_AsyncQueue')
 ? OraBooks_AsyncQueue::get_handler('partner_activity_check')
: null;
 $add_check(
 'async_partner_activity_handler',
 'Async handler: partner_activity_check',
 is_callable($partner_handler)
 );

 if (class_exists('OraBooks_Classification')) {
 global $wpdb;

 $classify_handler = class_exists('OraBooks_AsyncQueue')
 ? OraBooks_AsyncQueue::get_handler('classify_transaction')
: null;
 $add_check(
 'async_classify_transaction_handler',
 'Async handler: classify_transaction ',
 is_callable($classify_handler)
 );

 $rules_table = OraBooks_Database::table('classification_rules');
 $rules_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $rules_table)) === $rules_table;
 $add_check('table_classification_rules', 'Table exists: classification_rules', $rules_exists, $rules_table);

 $expenses_table = OraBooks_Database::table('expenses');
 $exp_cols = $wpdb->get_col("SHOW COLUMNS FROM {$expenses_table}", 0);
 $exp_ok = is_array($exp_cols) && in_array('classification_status', $exp_cols, true);
 $add_check(
 'expenses_classification_schema',
 'Expenses columns present',
 $exp_ok,
 is_array($exp_cols) ? implode(', ', array_intersect($exp_cols, ['classification_status', 'suggested_account_code'])): 'n/a'
 );
 }

 if (class_exists('OraBooks_Expenses')) {
 global $wpdb;

 $ocr_handler = class_exists('OraBooks_AsyncQueue')
 ? OraBooks_AsyncQueue::get_handler('process_expense_ocr')
: null;
 $add_check(
 'async_process_expense_ocr_handler',
 'Async handler: process_expense_ocr ',
 is_callable($ocr_handler)
 );

 $queue_table = OraBooks_Database::table('ocr_processing_queue');
 $queue_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $queue_table)) === $queue_table;
 $add_check('table_ocr_processing_queue', 'Table exists: ocr_processing_queue', $queue_exists, $queue_table);

 $line_items_table = OraBooks_Database::table('expense_line_items');
 $lines_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $line_items_table)) === $line_items_table;
 $add_check('table_expense_line_items', 'Table exists: expense_line_items', $lines_exists, $line_items_table);

 $settings_table = OraBooks_Database::table('expense_settings');
 $settings_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $settings_table)) === $settings_table;
 $add_check('table_expense_settings', 'Table exists: expense_settings ( Phase 4)', $settings_exists, $settings_table);

 $expenses_table = OraBooks_Database::table('expenses');
 $exp_cols = $wpdb->get_col("SHOW COLUMNS FROM {$expenses_table}", 0);
 $ocr_ok = is_array($exp_cols)
 && in_array('ocr_confidence', $exp_cols, true)
 && in_array('ocr_snapshot_hash', $exp_cols, true);
 $add_check(
 'expenses_ocr_schema',
 'Expenses OCR columns present',
 $ocr_ok,
 is_array($exp_cols) ? implode(', ', array_intersect($exp_cols, ['ocr_confidence', 'ocr_risk_level', 'ocr_snapshot_hash'])): 'n/a'
 );

 $ocr_cron = wp_next_scheduled('orabooks_expenses_ocr_process');
 $add_check(
 'cron_orabooks_expenses_ocr_process',
 'Cron scheduled: orabooks_expenses_ocr_process',
 $ocr_cron !== false,
 $ocr_cron ? gmdate('c', $ocr_cron): 'not scheduled'
 );
 }

 return [
 'ok' => $ok,
 'checks' => $checks,
 'timestamp' => current_time('mysql'),
 'environment' => [
 'is_multisite' => function_exists('is_multisite') && is_multisite(),
 'data_blog_id' => function_exists('orabooks_get_data_blog_id') ? orabooks_get_data_blog_id: null,
 'current_blog_id' => function_exists('get_current_blog_id') ? get_current_blog_id: null,
 'table_prefix' => $table_prefix,
 'plugin_version' => defined('ORABOOKS_VERSION') ? ORABOOKS_VERSION: null,
 'db_version_expected' => $expected_db_version,
 ],
 ];
 }
}
