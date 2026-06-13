<?php
/**
 * SL-013 – Rate Limiter Utility
 *
 * Reusable rate limiting for:
 * - Registration: 5 attempts/hour per IP
 * - Login: 5 failures/15 minutes per IP+email
 * - Subdomain check availability: 10 requests/minute
 *
 * Uses WordPress transients for storage (no custom tables needed).
 * All rate limit state is stored with TTL matching the window duration.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Rate_Limiter {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Check if an action is rate limited.
     *
     * @param string $action  Action identifier (e.g., 'register', 'login', 'check_subdomain')
     * @param string $key     Unique key for the limit (e.g., IP address, or IP+email combo)
     * @param int    $limit   Maximum number of attempts allowed
     * @param int    $window  Time window in seconds (e.g., 3600 = 1 hour)
     * @return array {
     *     @type bool   $allowed      Whether the action is allowed
     *     @type int    $remaining    Remaining attempts in the current window
     *     @type int    $retry_after  Seconds until the limit resets (0 if not limited)
     * }
     */
    public function check_rate_limit($action, $key, $limit, $window) {
        $transient_key = 'orabooks_rl_' . md5($action . '|' . $key);
        $data = get_transient($transient_key);

        if (false === $data) {
            // First attempt – initialise
            $data = array(
                'count'    => 0,
                'window_start' => time(),
            );
        }

        $elapsed = time() - $data['window_start'];

        // If the window has expired, reset
        if ($elapsed > $window) {
            $data = array(
                'count'    => 0,
                'window_start' => time(),
            );
            $elapsed = 0;
        }

        $remaining = max(0, $limit - $data['count']);
        $retry_after = ($data['count'] >= $limit) ? max(1, $window - $elapsed) : 0;

        return array(
            'allowed'     => $data['count'] < $limit,
            'remaining'   => $remaining,
            'retry_after' => $retry_after,
            'count'       => $data['count'],
        );
    }

    /**
     * Increment the counter for an action+key pair.
     * Must be called *after* a successful check_rate_limit() passes.
     *
     * @param string $action  Action identifier.
     * @param string $key     Unique key.
     * @param int    $limit   Maximum attempts.
     * @param int    $window  Window in seconds.
     * @return int  New count after increment.
     */
    public function increment($action, $key, $limit, $window) {
        $transient_key = 'orabooks_rl_' . md5($action . '|' . $key);
        $data = get_transient($transient_key);

        if (false === $data || (time() - $data['window_start']) > $window) {
            $data = array(
                'count'       => 0,
                'window_start' => time(),
            );
        }

        $data['count']++;
        $ttl = $window; // Transient TTL matches the window

        set_transient($transient_key, $data, $ttl);

        return $data['count'];
    }

    /**
     * Reset / clear the rate limit counter for a given action+key.
     * Useful after a successful login to clear failed-attempt tracking.
     *
     * @param string $action Action identifier.
     * @param string $key    Unique key.
     */
    public function reset($action, $key) {
        $transient_key = 'orabooks_rl_' . md5($action . '|' . $key);
        delete_transient($transient_key);
    }

    /**
     * Convenience: test and increment in one call.
     * Throws WP_Error if rate limited.
     *
     * @param string $action  Action identifier.
     * @param string $key     Unique key.
     * @param int    $limit   Max attempts.
     * @param int    $window  Window seconds.
     * @return true|WP_Error  True if allowed, WP_Error with 429 status if blocked.
     */
    public function check_and_increment($action, $key, $limit, $window) {
        $status = $this->check_rate_limit($action, $key, $limit, $window);

        if (!$status['allowed']) {
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    __('Too many attempts. Please try again in %d second(s).', 'orabooks'),
                    $status['retry_after']
                )
            );
        }

        $this->increment($action, $key, $limit, $window);
        return true;
    }

    /**
     * Get client IP address (respecting proxies).
     *
     * @return string
     */
    public static function get_client_ip() {
        $ip = '';
        $sources = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        );

        foreach ($sources as $source) {
            if (!empty($_SERVER[$source])) {
                $ip = $_SERVER[$source];
                // X-Forwarded-For can contain a comma-separated list; take the first
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return sanitize_text_field($ip);
                }
            }
        }

        return '0.0.0.0';
    }
}
