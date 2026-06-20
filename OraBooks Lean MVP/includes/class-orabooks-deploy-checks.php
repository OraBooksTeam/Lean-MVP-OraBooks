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
        ];
    }

    /**
     * Schedule missing MVP crons (idempotent). Returns hooks that were newly scheduled.
     *
     * @return string[]
     */
    public static function ensure_mvp_cron_schedules() {
        $repaired = [];

        foreach (self::mvp_cron_jobs() as $hook => $recurrence) {
            if (wp_next_scheduled($hook)) {
                continue;
            }

            wp_schedule_event(time(), $recurrence, $hook);
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

        $jwt_secret = get_option('orabooks_jwt_secret');
        $add_check('jwt_secret', 'JWT secret configured', !empty($jwt_secret));

        $db_version = get_option('orabooks_db_version');
        $expected_db_version = defined('ORABOOKS_DB_VERSION') ? ORABOOKS_DB_VERSION : '1.0.1';
        $add_check(
            'db_version',
            'Database schema version',
            $db_version === $expected_db_version,
            'expected ' . $expected_db_version . ', got ' . ($db_version ?: 'none')
        );

        $table_prefix = '';
        $run_table_checks = function () use (&$table_prefix, $add_check) {
            global $wpdb;

            $table_prefix = function_exists('orabooks_get_table_prefix')
                ? orabooks_get_table_prefix()
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
            ];

            foreach ($required_tables as $name) {
                $table = OraBooks_Database::table($name);
                $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
                $add_check('table_' . $name, 'Table exists: ' . $name, $exists, $table);
            }

            $invoices_table = OraBooks_Database::table('invoices');
            $columns = $wpdb->get_col("SHOW COLUMNS FROM {$invoices_table}", 0);
            $has_sl021_columns = is_array($columns)
                && in_array('payment_status', $columns, true)
                && in_array('paid_amount', $columns, true);
            $add_check(
                'invoices_schema',
                'Invoices SL-021 columns present',
                $has_sl021_columns,
                is_array($columns) ? implode(', ', $columns) : 'columns unavailable'
            );
        };

        if (function_exists('orabooks_with_data_blog')) {
            orabooks_with_data_blog($run_table_checks);
        } else {
            $run_table_checks();
        }

        $cron_hooks = array_keys(self::mvp_cron_jobs());
        foreach ($cron_hooks as $hook) {
            $next = wp_next_scheduled($hook);
            $add_check(
                'cron_' . $hook,
                'Cron scheduled: ' . $hook,
                $next !== false,
                $next ? gmdate('c', $next) : 'not scheduled'
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

        return [
            'ok' => $ok,
            'checks' => $checks,
            'timestamp' => current_time('mysql'),
            'environment' => [
                'is_multisite' => function_exists('is_multisite') && is_multisite(),
                'data_blog_id' => function_exists('orabooks_get_data_blog_id') ? orabooks_get_data_blog_id() : null,
                'current_blog_id' => function_exists('get_current_blog_id') ? get_current_blog_id() : null,
                'table_prefix' => $table_prefix,
                'plugin_version' => defined('ORABOOKS_VERSION') ? ORABOOKS_VERSION : null,
                'db_version_expected' => $expected_db_version,
            ],
        ];
    }
}
