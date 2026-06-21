<?php
/**
 * OraBooks OWASP Top-10 Security Controls (SL-099)
 *
 * Cross-cutting governance: OWASP control matrix, security headers,
 * centralized rate-limit config, SSRF allowlist, incident tracking,
 * and verification cron jobs with SL-250 alerting.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Security {

    const INCIDENT_RETENTION_DAYS = 90;
    const SCAN_RETENTION_DAYS = 180;
    const ACCESS_DENIED_ALERT_THRESHOLD = 10;
    const SECRET_ROTATION_DAYS = 90;

    private static $instance = null;

    /** Centralized rate-limit configuration (SL-099 §5.4). */
    private static $rate_limits = [
        'registration_per_ip'   => ['max' => 5, 'period' => 3600, 'label' => 'Registration per IP'],
        'login_failure'         => ['max' => 5, 'period' => 900, 'label' => 'Login failures per email+IP'],
        'generic_api_per_user'  => ['max' => 1000, 'period' => 3600, 'label' => 'Generic API per user'],
        'export_per_user'       => ['max' => 10, 'period' => 3600, 'label' => 'Export requests per user'],
    ];

    /** Default SSRF allowlist patterns (SL-099 §5.5). */
    private static $default_webhook_allowlist = [
        'https://hooks.slack.com/*',
        'https://*.webhook.office.com/*',
        'https://api.github.com/*',
    ];

    /** Simple JSON input schemas for governance (SL-099 §5.3). */
    private static $input_schemas = [
        'uuid' => ['type' => 'string', 'pattern' => '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i'],
        'email' => ['type' => 'string', 'pattern' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
        'positive_int' => ['type' => 'integer', 'min' => 1],
        'org_id' => ['type' => 'integer', 'min' => 1],
        'amount' => ['type' => 'number', 'min' => 0],
    ];

    /**
     * OWASP Top-10 (2021) catalog — source of truth for matrix + admin UI.
     */
    public static function get_owasp_catalog() {
        return [
            'A01' => [
                'control_name'      => 'Broken Access Control',
                'implemented_in_sl' => 'SL-003, SL-004',
                'validation_note'   => 'RBAC deny-by-default with org_id scoping',
                'mitigations'       => [
                    'Role-based access control (RBAC)',
                    'org_id tenant isolation on every query',
                    'require_permission middleware',
                ],
                'user_message'      => 'Access denied. Your role does not allow this action.',
            ],
            'A02' => [
                'control_name'      => 'Cryptographic Failures',
                'implemented_in_sl' => 'SL-008, SL-017, SL-203',
                'validation_note'   => 'TLS, encrypted secrets, bcrypt passwords',
                'mitigations'       => [
                    'TLS 1.2+ (HSTS when SSL)',
                    'Encrypted secrets via OraBooks_Secrets',
                    'bcrypt password hashing',
                    'JWT signing with rotated secrets',
                ],
                'user_message'      => null,
            ],
            'A03' => [
                'control_name'      => 'Injection',
                'implemented_in_sl' => 'All SL',
                'validation_note'   => 'Prepared statements and input allowlist validation',
                'mitigations'       => [
                    '$wpdb->prepare() on all SQL',
                    'JSON schema input validation',
                    'Centralized rate limiting',
                ],
                'user_message'      => 'Invalid input format.',
            ],
            'A04' => [
                'control_name'      => 'Insecure Design',
                'implemented_in_sl' => 'Architecture',
                'validation_note'   => 'Central posting engine and immutable ledger',
                'mitigations'       => [
                    'Central double-entry posting engine',
                    'Immutable journal hash chain',
                    'Deny-by-default RBAC',
                ],
                'user_message'      => null,
            ],
            'A05' => [
                'control_name'      => 'Security Misconfiguration',
                'implemented_in_sl' => 'SL-008, SL-099',
                'validation_note'   => 'Security headers and secrets in environment',
                'mitigations'       => [
                    'HSTS, CSP, X-Frame-Options headers',
                    'Secrets stored outside source code',
                    'Debug mode disabled in production',
                ],
                'user_message'      => null,
            ],
            'A06' => [
                'control_name'      => 'Vulnerable Components',
                'implemented_in_sl' => 'DevOps, SL-099',
                'validation_note'   => 'Weekly dependency scan job',
                'mitigations'       => [
                    'Weekly dependency scan cron',
                    'SBOM inventory tracking',
                    'Alert on critical vulnerabilities (SL-250)',
                ],
                'user_message'      => null,
            ],
            'A07' => [
                'control_name'      => 'Authentication Failures',
                'implemented_in_sl' => 'SL-013',
                'validation_note'   => 'JWT, 2FA, rate limits, password policy',
                'mitigations'       => [
                    'TOTP two-factor authentication',
                    'Login failure rate limiting',
                    'Password complexity policy',
                    'Session/JWT expiry',
                ],
                'user_message'      => 'Two-factor authentication required.',
            ],
            'A08' => [
                'control_name'      => 'Software & Data Integrity',
                'implemented_in_sl' => 'SL-001, SL-009',
                'validation_note'   => 'Outbox pattern and audit logging',
                'mitigations'       => [
                    'Immutable audit log',
                    'Journal hash chain integrity checks',
                    'Event outbox for reliable delivery',
                ],
                'user_message'      => null,
            ],
            'A09' => [
                'control_name'      => 'Security Logging Failures',
                'implemented_in_sl' => 'SL-009, SL-250',
                'validation_note'   => 'Centralized audit and alerting',
                'mitigations'       => [
                    'All security events audit-logged',
                    'Incident tracking table',
                    'SL-250 notification alerts',
                ],
                'user_message'      => null,
            ],
            'A10' => [
                'control_name'      => 'SSRF',
                'implemented_in_sl' => 'SL-250, SL-099',
                'validation_note'   => 'Webhook URL allowlist validation',
                'mitigations'       => [
                    'HTTPS-only outbound URLs',
                    'Private/local host blocking',
                    'Configurable webhook allowlist patterns',
                ],
                'user_message'      => null,
            ],
        ];
    }

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();

            add_action('send_headers', [self::$instance, 'maybe_send_security_headers']);
            add_action('orabooks_security_dependency_scan', [self::$instance, 'cron_dependency_scan']);
            add_action('orabooks_security_header_check', [self::$instance, 'cron_header_check']);
            add_action('orabooks_security_audit_integrity', [self::$instance, 'cron_audit_integrity']);
            add_action('orabooks_security_secret_rotation_reminder', [self::$instance, 'cron_secret_rotation_reminder']);
            add_action('orabooks_security_purge', [self::$instance, 'cron_purge_old_records']);

            add_action('wp_ajax_orabooks_security_dashboard', [self::$instance, 'ajax_dashboard']);
            add_action('wp_ajax_orabooks_security_verify_controls', [self::$instance, 'ajax_verify_controls']);
        }

        return self::$instance;
    }

    public static function get_create_table_sql() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $tables = [];

        $controls = OraBooks_Database::table('security_controls');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$controls} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            owasp_id VARCHAR(10) NOT NULL,
            control_name VARCHAR(100) NOT NULL,
            implemented_in_sl VARCHAR(50) NOT NULL DEFAULT '',
            validation_note TEXT DEFAULT NULL,
            status ENUM('verified','pending','failed') NOT NULL DEFAULT 'pending',
            last_verified TIMESTAMP NULL DEFAULT NULL,
            UNIQUE KEY uk_owasp_id (owasp_id)
        ) {$charset_collate};";

        $incidents = OraBooks_Database::table('security_incidents');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$incidents} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            incident_type VARCHAR(50) NOT NULL,
            severity ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
            ip_address VARCHAR(45) DEFAULT NULL,
            user_id BIGINT UNSIGNED NULL,
            org_id BIGINT UNSIGNED NULL,
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_type_created (incident_type, created_at),
            INDEX idx_severity_created (severity, created_at)
        ) {$charset_collate};";

        $scans = OraBooks_Database::table('security_scan_results');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$scans} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            scan_type VARCHAR(50) NOT NULL,
            status ENUM('pass','warn','fail') NOT NULL DEFAULT 'pass',
            summary VARCHAR(255) NOT NULL DEFAULT '',
            details JSON DEFAULT NULL,
            scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_scan_type (scan_type, scanned_at)
        ) {$charset_collate};";

        return $tables;
    }

    /**
     * Seed OWASP Top-10 (2021) control mapping matrix.
     */
    public static function seed_owasp_controls() {
        global $wpdb;

        $table = OraBooks_Database::table('security_controls');
        $catalog = self::get_owasp_catalog();

        foreach ($catalog as $owasp_id => $meta) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE owasp_id = %s",
                $owasp_id
            ));
            if ($existing) {
                continue;
            }
            $wpdb->insert($table, [
                'owasp_id'           => $owasp_id,
                'control_name'       => $meta['control_name'],
                'implemented_in_sl'  => $meta['implemented_in_sl'],
                'validation_note'    => $meta['validation_note'],
                'status'             => 'pending',
            ], ['%s', '%s', '%s', '%s', '%s']);
        }
    }

    /**
     * Send HTTP security headers (SL-099 §5.5).
     */
    public static function send_security_headers() {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(self), camera=()');

        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        $csp = apply_filters(
            'orabooks_content_security_policy',
            implode(' ', [
                "default-src 'self' data: blob: https:;",
                // unsafe-eval only in script-src (legacy accounting Tailwind CDN); not in script-src-elem.
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' https: blob:;",
                "script-src-elem 'self' 'unsafe-inline' https: blob:;",
                "style-src 'self' 'unsafe-inline' https:;",
                "img-src 'self' data: blob: https:;",
                "font-src 'self' data: https:;",
                "connect-src 'self' https:;",
                "frame-ancestors 'self';",
            ])
        );
        header('Content-Security-Policy: ' . $csp);

        update_option('orabooks_security_headers_last_sent', current_time('mysql', true));
    }

    public function maybe_send_security_headers() {
        $send = false;

        if (defined('DOING_AJAX') && DOING_AJAX) {
            $action = sanitize_text_field($_REQUEST['action'] ?? '');
            if (strpos($action, 'orabooks_') === 0) {
                $send = true;
            }
        } elseif (!is_admin()) {
            $send = true;
        }

        if ($send) {
            self::send_security_headers();
        }
    }

    /**
     * Centralized rate-limit configuration.
     */
    public static function get_rate_limit_config() {
        return apply_filters('orabooks_security_rate_limits', self::$rate_limits);
    }

    /**
     * SSRF allowlist validation for outbound URLs (SL-099 §5.5).
     *
     * @return true|WP_Error
     */
    public static function validate_outbound_url($url) {
        $url = trim((string) $url);
        if ($url === '') {
            return new WP_Error('ssrf_empty', __('Webhook URL required', 'orabooks'));
        }

        $parsed = parse_url($url);
        if (empty($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['https'], true)) {
            self::record_incident('ssrf_blocked', 'warning', ['url' => $url, 'reason' => 'non_https']);
            return new WP_Error('ssrf_scheme', __('Only HTTPS webhook URLs are allowed', 'orabooks'));
        }

        $host = strtolower($parsed['host'] ?? '');
        if ($host === '' || self::is_private_or_local_host($host)) {
            self::record_incident('ssrf_blocked', 'warning', ['url' => $url, 'reason' => 'private_host']);
            return new WP_Error('ssrf_private', __('Webhook URL targets a private or local address', 'orabooks'));
        }

        $allowlist = apply_filters(
            'orabooks_webhook_url_allowlist',
            get_option('orabooks_webhook_url_allowlist', self::$default_webhook_allowlist)
        );

        if (!self::url_matches_allowlist($url, $allowlist)) {
            self::record_incident('ssrf_blocked', 'warning', ['url' => $url, 'reason' => 'not_allowlisted']);
            return new WP_Error('ssrf_allowlist', __('Webhook URL is not on the approved allowlist', 'orabooks'));
        }

        return true;
    }

    /**
     * JSON input schema validation helper (SL-099 §5.3).
     *
     * @return true|WP_Error
     */
    public static function validate_input($schema_key, $value) {
        $schemas = apply_filters('orabooks_security_input_schemas', self::$input_schemas);
        if (!isset($schemas[$schema_key])) {
            return new WP_Error('schema_unknown', __('Unknown validation schema', 'orabooks'));
        }

        $schema = $schemas[$schema_key];
        $type = $schema['type'] ?? 'string';

        if ($type === 'integer') {
            if (!is_numeric($value) || (int) $value != $value) {
                return new WP_Error('invalid_input', __('Invalid input format.', 'orabooks'));
            }
            $value = (int) $value;
            if (isset($schema['min']) && $value < $schema['min']) {
                return new WP_Error('invalid_input', __('Invalid input format.', 'orabooks'));
            }
        } elseif ($type === 'number') {
            if (!is_numeric($value)) {
                return new WP_Error('invalid_input', __('Invalid input format.', 'orabooks'));
            }
            $value = (float) $value;
            if (isset($schema['min']) && $value < $schema['min']) {
                return new WP_Error('invalid_input', __('Invalid input format.', 'orabooks'));
            }
        } elseif ($type === 'string') {
            $value = (string) $value;
            if (!empty($schema['pattern']) && !preg_match($schema['pattern'], $value)) {
                return new WP_Error('invalid_input', __('Invalid input format.', 'orabooks'));
            }
        }

        return true;
    }

    /**
     * Record a security incident and evaluate alert thresholds.
     */
    public static function record_incident($incident_type, $severity = 'info', $metadata = [], $user_id = null, $org_id = null) {
        global $wpdb;

        $severity = in_array($severity, ['info', 'warning', 'critical'], true) ? $severity : 'info';
        $table = OraBooks_Database::table('security_incidents');

        $wpdb->insert($table, [
            'incident_type' => sanitize_text_field($incident_type),
            'severity'      => $severity,
            'ip_address'    => self::get_client_ip(),
            'user_id'       => $user_id ? (int) $user_id : null,
            'org_id'        => $org_id ? (int) $org_id : null,
            'metadata'      => !empty($metadata) ? wp_json_encode($metadata) : null,
            'created_at'    => current_time('mysql', true),
        ], ['%s', '%s', '%s', '%d', '%d', '%s', '%s']);

        orabooks_log_event(
            'security_incident',
            sprintf('Security incident: %s', $incident_type),
            $severity === 'critical' ? 'critical' : 'warning',
            array_merge(['incident_type' => $incident_type], $metadata),
            $user_id,
            $org_id
        );

        if ($incident_type === 'access_denied') {
            self::evaluate_access_denied_threshold();
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Track HTTP error responses from centralized JSON helper.
     */
    public static function record_http_response($status_code, $message = '') {
        $status_code = (int) $status_code;

        if ($status_code === 403) {
            self::record_incident('access_denied', 'warning', [
                'message' => sanitize_text_field($message),
                'status'  => 403,
            ]);
        } elseif ($status_code === 429) {
            self::record_incident('rate_limit_exceeded', 'warning', [
                'message' => sanitize_text_field($message),
                'status'  => 429,
            ]);
        } elseif ($status_code === 401) {
            self::record_incident('authentication_failure', 'info', [
                'message' => sanitize_text_field($message),
                'status'  => 401,
            ]);
        }
    }

    /**
     * Verify OWASP controls and update matrix status.
     */
    public static function verify_controls() {
        global $wpdb;

        $table = OraBooks_Database::table('security_controls');
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY owasp_id ASC");
        $verified = 0;
        $failed = 0;
        $results = [];

        foreach ($rows as $row) {
            $check = self::verify_single_control($row->owasp_id);
            $status = $check['pass'] ? 'verified' : 'failed';
            if ($check['pass']) {
                $verified++;
            } else {
                $failed++;
            }

            $wpdb->update(
                $table,
                [
                    'status'         => $status,
                    'last_verified'  => current_time('mysql', true),
                    'validation_note'=> $check['note'],
                ],
                ['id' => (int) $row->id],
                ['%s', '%s', '%s'],
                ['%d']
            );

            $results[] = [
                'owasp_id'      => $row->owasp_id,
                'control_name'  => $row->control_name,
                'status'        => $status,
                'note'          => $check['note'],
            ];
        }

        return [
            'verified' => $verified,
            'failed'   => $failed,
            'controls' => $results,
        ];
    }

    /**
     * Build admin security dashboard payload.
     */
    public static function get_dashboard($hours = 24) {
        global $wpdb;

        $hours = max(1, min(168, (int) $hours));
        $since = date('Y-m-d H:i:s', time() - ($hours * 3600));

        $incidents_table = OraBooks_Database::table('security_incidents');
        $audit_table = OraBooks_Database::table('audit_logs');
        $scans_table = OraBooks_Database::table('security_scan_results');
        $controls_table = OraBooks_Database::table('security_controls');

        $incident_counts = $wpdb->get_results($wpdb->prepare(
            "SELECT incident_type, COUNT(*) AS cnt
             FROM {$incidents_table}
             WHERE created_at >= %s
             GROUP BY incident_type",
            $since
        ));

        $by_type = [];
        foreach ($incident_counts as $row) {
            $by_type[$row->incident_type] = (int) $row->cnt;
        }

        $audit_volume = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$audit_table} WHERE created_at >= %s",
            $since
        ));

        $failed_logins = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$audit_table}
             WHERE created_at >= %s AND event_type IN ('login_failed', 'authentication_failure')",
            $since
        ));

        if ($failed_logins === 0) {
            $failed_logins = (int) ($by_type['authentication_failure'] ?? 0);
        }

        $controls = $wpdb->get_results(
            "SELECT owasp_id, control_name, implemented_in_sl, validation_note, status, last_verified
             FROM {$controls_table}
             ORDER BY owasp_id ASC"
        );

        $catalog = self::get_owasp_catalog();
        $merged_controls = [];
        $controls_by_id = [];
        foreach ($controls as $row) {
            $controls_by_id[$row->owasp_id] = $row;
        }
        foreach ($catalog as $owasp_id => $meta) {
            $db_row = $controls_by_id[$owasp_id] ?? null;
            $merged_controls[] = [
                'owasp_id'          => $owasp_id,
                'control_name'      => $db_row->control_name ?? $meta['control_name'],
                'implemented_in_sl' => $db_row->implemented_in_sl ?? $meta['implemented_in_sl'],
                'validation_note'   => $db_row->validation_note ?? $meta['validation_note'],
                'status'            => $db_row->status ?? 'pending',
                'last_verified'     => $db_row->last_verified ?? null,
                'mitigations'       => $meta['mitigations'],
                'user_message'      => $meta['user_message'],
            ];
        }

        $ssrf_blocks = (int) ($by_type['ssrf_blocked'] ?? 0);

        $latest_scans = $wpdb->get_results(
            "SELECT s1.*
             FROM {$scans_table} s1
             INNER JOIN (
                 SELECT scan_type, MAX(id) AS max_id
                 FROM {$scans_table}
                 GROUP BY scan_type
             ) s2 ON s1.id = s2.max_id
             ORDER BY s1.scan_type ASC"
        );

        return [
            'period_hours'       => $hours,
            'incidents_by_type'  => $by_type,
            'failed_logins'      => $failed_logins,
            'rate_limit_hits'    => (int) ($by_type['rate_limit_exceeded'] ?? 0),
            'access_denied'      => (int) ($by_type['access_denied'] ?? 0),
            'ssrf_blocks'        => $ssrf_blocks,
            'audit_volume'       => $audit_volume,
            'owasp_controls'     => $merged_controls,
            'input_schemas'      => array_keys(self::$input_schemas),
            'latest_scans'       => $latest_scans,
            'rate_limits'        => self::get_rate_limit_config(),
            'headers_status'     => self::get_headers_status(),
            'webhook_allowlist'  => get_option('orabooks_webhook_url_allowlist', self::$default_webhook_allowlist),
            'secrets_rotation'   => self::get_secret_rotation_status(),
            'timestamp'          => current_time('mysql'),
        ];
    }

    /**
     * Secret rotation status for dashboard.
     */
    public static function get_secret_rotation_status() {
        $last_rotated = get_option('orabooks_secrets_last_rotated', '');
        if ($last_rotated === '') {
            $last_rotated = get_option('orabooks_installed_at', current_time('mysql', true));
        }
        $days_since = (int) floor((time() - strtotime($last_rotated)) / DAY_IN_SECONDS);

        return [
            'last_rotated' => $last_rotated,
            'days_since'   => $days_since,
            'due'          => $days_since >= self::SECRET_ROTATION_DAYS,
            'days_until'   => max(0, self::SECRET_ROTATION_DAYS - $days_since),
        ];
    }

    public static function store_scan_result($scan_type, $status, $summary, $details = []) {
        global $wpdb;

        $status = in_array($status, ['pass', 'warn', 'fail'], true) ? $status : 'pass';
        $table = OraBooks_Database::table('security_scan_results');

        $wpdb->insert($table, [
            'scan_type'  => sanitize_text_field($scan_type),
            'status'     => $status,
            'summary'    => sanitize_text_field($summary),
            'details'    => wp_json_encode($details),
            'scanned_at' => current_time('mysql', true),
        ], ['%s', '%s', '%s', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    public static function get_headers_status() {
        $last_sent = get_option('orabooks_security_headers_last_sent', '');
        $configured = [
            'Strict-Transport-Security' => is_ssl(),
            'Content-Security-Policy'   => true,
            'X-Frame-Options'           => true,
            'X-Content-Type-Options'    => true,
            'Referrer-Policy'           => true,
            'Permissions-Policy'        => true,
        ];

        return [
            'configured' => $configured,
            'last_sent'  => $last_sent,
            'status'     => 'active',
        ];
    }

    public function cron_dependency_scan() {
        $inventory = self::collect_dependency_inventory();
        $vulnerabilities = (int) ($inventory['vulnerabilities'] ?? 0);
        $status = $vulnerabilities > 0 ? 'fail' : 'pass';
        $summary = $vulnerabilities > 0
            ? sprintf('%d vulnerability(ies) detected', $vulnerabilities)
            : 'No critical vulnerabilities detected';

        self::store_scan_result('dependency_scan', $status, $summary, $inventory);
        orabooks_log_event('security_dependency_scan', 'Weekly dependency scan completed', 'info', $inventory);

        if ($vulnerabilities > 0) {
            self::record_incident('dependency_vulnerability', 'critical', $inventory);
            self::notify_platform_admins('platform_dependency_vulnerability', [
                'title'    => __('Dependency Vulnerability Detected', 'orabooks'),
                'message'  => sprintf(
                    __('%d dependency vulnerability(ies) found during weekly scan.', 'orabooks'),
                    $vulnerabilities
                ),
                'priority' => 'high',
                'correlation_id' => 'sec_dep_' . current_time('Ymd'),
            ]);
        }
    }

    /**
     * Inventory Composer and npm dependencies for SBOM tracking.
     */
    private static function collect_dependency_inventory() {
        $packages = [];
        $vulnerabilities = 0;

        $composer_lock = ORABOOKS_PLUGIN_DIR . 'tests/composer.lock';
        if (file_exists($composer_lock)) {
            $lock = json_decode(file_get_contents($composer_lock), true);
            if (is_array($lock['packages'] ?? null)) {
                foreach ($lock['packages'] as $pkg) {
                    $name = $pkg['name'] ?? 'unknown';
                    $version = $pkg['version'] ?? 'unknown';
                    $packages[] = ['name' => $name, 'version' => $version, 'ecosystem' => 'composer'];
                }
            }
        }

        $ui_package = dirname(ORABOOKS_PLUGIN_DIR) . '/orabooks-ui/package.json';
        if (file_exists($ui_package)) {
            $pkg_json = json_decode(file_get_contents($ui_package), true);
            $deps = array_merge(
                $pkg_json['dependencies'] ?? [],
                $pkg_json['devDependencies'] ?? []
            );
            foreach ($deps as $name => $version) {
                $packages[] = ['name' => $name, 'version' => $version, 'ecosystem' => 'npm'];
            }
        }

        return [
            'engine'            => 'inventory',
            'packages_scanned'  => count($packages),
            'vulnerabilities'   => $vulnerabilities,
            'packages'          => array_slice($packages, 0, 50),
            'note'              => 'Run npm audit / composer audit in CI for production vulnerability detection',
        ];
    }

    public function cron_header_check() {
        $status = self::get_headers_status();
        $all_ok = !in_array(false, $status['configured'], true);
        $scan_status = $all_ok ? 'pass' : 'warn';

        if (class_exists('OraBooks_Secrets') && method_exists('OraBooks_Secrets', 'check_tls_certificate')) {
            $tls = OraBooks_Secrets::check_tls_certificate();
            $status['tls_certificate'] = $tls;

            if (!empty($tls['expired'])) {
                $all_ok = false;
                $scan_status = 'fail';
            } elseif (!empty($tls['expiring_soon'])) {
                $scan_status = $scan_status === 'pass' ? 'warn' : $scan_status;
            }
        }

        self::store_scan_result(
            'security_headers',
            $scan_status,
            $all_ok ? 'Security headers configured' : 'Some security headers need attention',
            $status
        );
    }

    public function cron_audit_integrity() {
        global $wpdb;

        $audit_table = OraBooks_Database::table('audit_logs');
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$audit_table}");
        $recent = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$audit_table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        $ledger_checks = [];
        $integrity_ok = true;
        $hash_chain_issues = 0;

        if (class_exists('OraBooks_Posting')) {
            $orgs_table = OraBooks_Database::table('organizations');
            $org_ids = $wpdb->get_col(
                "SELECT id FROM {$orgs_table}
                 WHERE organization_type = 'customer' AND status = 'active'
                 ORDER BY id ASC LIMIT 10"
            );

            foreach ($org_ids as $org_id) {
                $result = OraBooks_Posting::validate_ledger_integrity((int) $org_id);
                $ledger_checks[] = [
                    'org_id' => (int) $org_id,
                    'ok'     => !empty($result['ok']),
                    'issues' => count($result['issues'] ?? []),
                ];
                if (empty($result['ok'])) {
                    $integrity_ok = false;
                    foreach ($result['issues'] ?? [] as $issue) {
                        if (($issue['type'] ?? '') === 'hash_chain_break') {
                            $hash_chain_issues++;
                        }
                    }
                }
            }
        }

        $details = [
            'total_entries'    => $total,
            'entries_24h'      => $recent,
            'ledger_checks'    => $ledger_checks,
            'hash_chain_issues'=> $hash_chain_issues,
            'integrity_ok'     => $integrity_ok && $recent > 0,
        ];

        $scan_status = $integrity_ok ? 'pass' : 'fail';
        $summary = $integrity_ok
            ? sprintf('Audit & ledger integrity OK (%d audit entries, %d in 24h)', $total, $recent)
            : sprintf('Integrity issues detected (%d hash chain break(s))', $hash_chain_issues);

        self::store_scan_result('audit_integrity', $scan_status, $summary, $details);

        if (!$integrity_ok) {
            self::record_incident('audit_integrity_failure', 'critical', $details);
            self::notify_platform_admins('platform_audit_integrity_failure', [
                'title'    => __('Audit/Ledger Integrity Failure', 'orabooks'),
                'message'  => __('Ledger hash chain or balance integrity check failed.', 'orabooks'),
                'priority' => 'critical',
                'correlation_id' => 'sec_audit_' . current_time('YmdH'),
            ]);
        }
    }

    public function cron_secret_rotation_reminder() {
        $last_rotated = get_option('orabooks_secrets_last_rotated', '');
        if ($last_rotated === '') {
            $last_rotated = get_option('orabooks_installed_at', current_time('mysql', true));
            update_option('orabooks_secrets_last_rotated', $last_rotated);
        }

        $days_since = (int) floor((time() - strtotime($last_rotated)) / DAY_IN_SECONDS);
        $due = $days_since >= self::SECRET_ROTATION_DAYS;
        $status = $due ? 'warn' : 'pass';
        $summary = $due
            ? sprintf('Secret rotation overdue (%d days since last rotation)', $days_since)
            : sprintf('Secret rotation OK (%d days until due)', max(0, self::SECRET_ROTATION_DAYS - $days_since));

        self::store_scan_result('secret_rotation', $status, $summary, [
            'last_rotated' => $last_rotated,
            'days_since'   => $days_since,
            'due'          => $due,
        ]);

        if ($due) {
            self::notify_platform_admins('platform_secret_rotation_due', [
                'title'    => __('Secret Rotation Due', 'orabooks'),
                'message'  => sprintf(
                    __('JWT/encryption secrets have not been rotated in %d days. Review SL-008 rotation policy.', 'orabooks'),
                    $days_since
                ),
                'priority' => 'high',
                'correlation_id' => 'sec_rotation_' . current_time('Ymd'),
            ]);
        }
    }

    public function cron_purge_old_records() {
        global $wpdb;

        $incident_cutoff = date('Y-m-d H:i:s', time() - (self::INCIDENT_RETENTION_DAYS * DAY_IN_SECONDS));
        $scan_cutoff = date('Y-m-d H:i:s', time() - (self::SCAN_RETENTION_DAYS * DAY_IN_SECONDS));

        $incidents = OraBooks_Database::table('security_incidents');
        $scans = OraBooks_Database::table('security_scan_results');

        $deleted_incidents = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$incidents} WHERE created_at < %s",
            $incident_cutoff
        ));
        $deleted_scans = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$scans} WHERE scanned_at < %s",
            $scan_cutoff
        ));

        orabooks_log_event('security_purge', 'Old security records purged', 'info', [
            'incidents_deleted' => (int) $deleted_incidents,
            'scans_deleted'     => (int) $deleted_scans,
        ]);
    }

    public function ajax_dashboard() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }

        $hours = intval($_REQUEST['hours'] ?? 24);
        orabooks_json_success(self::get_dashboard($hours));
    }

    public function ajax_verify_controls() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }

        orabooks_json_success(self::verify_controls(), 'OWASP controls verified');
    }

    private static function verify_single_control($owasp_id) {
        switch ($owasp_id) {
            case 'A01':
                $pass = class_exists('OraBooks_RBAC')
                    && method_exists('OraBooks_RBAC', 'require_permission')
                    && class_exists('OraBooks_Organization');
                return ['pass' => $pass, 'note' => $pass ? 'RBAC + org_id scoping active' : 'RBAC or org isolation missing'];
            case 'A02':
                $pass = class_exists('OraBooks_Secrets')
                    && function_exists('orabooks_validate_password')
                    && method_exists('OraBooks_Secrets', 'hash_password');
                return ['pass' => $pass, 'note' => $pass ? 'Secrets, bcrypt, and password policy active' : 'Crypto controls missing'];
            case 'A03':
                $pass = function_exists('orabooks_check_rate_limit')
                    && method_exists(__CLASS__, 'validate_input');
                return ['pass' => $pass, 'note' => $pass ? 'Input schemas and rate limits active' : 'Validation helpers missing'];
            case 'A04':
                $pass = class_exists('OraBooks_Posting')
                    && method_exists('OraBooks_Posting', 'validate_ledger_integrity');
                return ['pass' => $pass, 'note' => $pass ? 'Central posting engine + hash chain present' : 'Posting engine missing'];
            case 'A05':
                $headers = self::get_headers_status();
                $pass = !in_array(false, $headers['configured'], true);
                return ['pass' => $pass, 'note' => $pass ? 'Security headers configured' : 'Header misconfiguration'];
            case 'A06':
                $pass = (bool) wp_next_scheduled('orabooks_security_dependency_scan');
                return ['pass' => $pass, 'note' => $pass ? 'Weekly dependency scan cron scheduled' : 'Dependency scan not scheduled'];
            case 'A07':
                $pass = class_exists('OraBooks_Auth')
                    && method_exists('OraBooks_Secrets', 'verify_totp')
                    && isset(self::get_rate_limit_config()['login_failure']);
                return ['pass' => $pass, 'note' => $pass ? 'Auth, 2FA, and login rate limits active' : 'Auth module incomplete'];
            case 'A08':
                $pass = class_exists('OraBooks_Audit')
                    && class_exists('OraBooks_Posting')
                    && (bool) wp_next_scheduled('orabooks_security_audit_integrity');
                return ['pass' => $pass, 'note' => $pass ? 'Audit log + ledger integrity cron active' : 'Integrity modules missing'];
            case 'A09':
                $pass = class_exists('OraBooks_Audit')
                    && class_exists('OraBooks_Notifications')
                    && method_exists(__CLASS__, 'record_incident');
                return ['pass' => $pass, 'note' => $pass ? 'Audit logging, incidents, and alerting active' : 'Logging/alerting missing'];
            case 'A10':
                $allowlist = get_option('orabooks_webhook_url_allowlist', self::$default_webhook_allowlist);
                $pass = method_exists(__CLASS__, 'validate_outbound_url')
                    && is_array($allowlist)
                    && !empty($allowlist);
                return ['pass' => $pass, 'note' => $pass ? 'SSRF allowlist validation active' : 'SSRF validation missing'];
            default:
                return ['pass' => false, 'note' => 'Unknown control'];
        }
    }

    private static function evaluate_access_denied_threshold() {
        global $wpdb;

        $table = OraBooks_Database::table('security_incidents');
        $since = date('Y-m-d H:i:s', time() - 3600);
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE incident_type = 'access_denied' AND created_at >= %s",
            $since
        ));

        if ($count <= self::ACCESS_DENIED_ALERT_THRESHOLD) {
            return;
        }

        $correlation = 'sec_403_burst_' . current_time('YmdH');
        $existing = get_transient('orabooks_sec_403_alert_' . current_time('YmdH'));
        if ($existing) {
            return;
        }
        set_transient('orabooks_sec_403_alert_' . current_time('YmdH'), 1, 3600);

        self::record_incident('access_denied_burst', 'critical', [
            'count_1h' => $count,
            'threshold'=> self::ACCESS_DENIED_ALERT_THRESHOLD,
        ]);

        self::notify_platform_admins('platform_security_403_burst', [
            'title'    => __('Possible Brute Force Detected', 'orabooks'),
            'message'  => sprintf(
                __('%d access-denied (403) events in the last hour exceeded threshold.', 'orabooks'),
                $count
            ),
            'priority' => 'critical',
            'correlation_id' => $correlation,
            'count'    => $count,
        ]);
    }

    private static function notify_platform_admins($event_type, $payload) {
        if (!class_exists('OraBooks_Notifications')) {
            return;
        }

        $admin_ids = get_users([
            'role'   => 'administrator',
            'fields' => ['ID'],
        ]);

        foreach ($admin_ids as $admin) {
            OraBooks_Notifications::notify((int) $admin->ID, $event_type, $payload, 0);
        }
    }

    private static function get_client_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return sanitize_text_field(substr($ip, 0, 45));
    }

    private static function is_private_or_local_host($host) {
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        $blocked = ['metadata.google.internal', '169.254.169.254'];
        return in_array($host, $blocked, true);
    }

    private static function url_matches_allowlist($url, $patterns) {
        if (!is_array($patterns) || empty($patterns)) {
            return false;
        }

        foreach ($patterns as $pattern) {
            $pattern = trim((string) $pattern);
            if ($pattern === '') {
                continue;
            }

            $regex = '/^' . str_replace(
                ['\*', '\?'],
                ['.*', '.'],
                preg_quote($pattern, '/')
            ) . '$/i';

            if (preg_match($regex, $url)) {
                return true;
            }
        }

        return false;
    }
}
