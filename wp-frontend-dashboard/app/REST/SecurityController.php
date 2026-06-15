<?php

namespace WPFD\REST;

/**
 * Security Controller - Handles Two-Factor Authentication settings in the frontend dashboard.
 * Renders the TFA shortcode [twofactor_user_settings] via REST API for AJAX-based section loading.
 */
class SecurityController
{

    /**
     * Register REST API routes.
     */
    public function register()
    {
        $namespace = 'wpfd/v1';

        // Security/TFA settings endpoint
        register_rest_route($namespace, '/dashboard/security', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_security_settings'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        // TFA enable/disable action endpoint
        register_rest_route($namespace, '/dashboard/security/tfa', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'save_tfa_settings'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
    }

    /**
     * Check if user is logged in and has basic access.
     */
    public function check_permission()
    {
        if (!is_user_logged_in()) {
            return false;
        }

        if (is_multisite() && !is_user_member_of_blog(get_current_user_id(), get_current_blog_id())) {
            return false;
        }

        return current_user_can('read');
    }

    /**
     * Get the TFA settings HTML.
     * Uses the existing [twofactor_user_settings] shortcode from the Simba TFA plugin.
     *
     * @return array
     */
    public function get_security_settings()
    {
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        // Check if Simba TFA class exists
        $tfa_available = class_exists('Simba_Two_Factor_Authentication_1');
        $tfa_enabled = false;
        $tfa_html = '';

        if ($tfa_available) {
            // Get the global Simba TFA instance
            global $simba_tfa, $tfa_frontend;

            if (isset($simba_tfa) && is_object($simba_tfa)) {
                $tfa_enabled = $simba_tfa->is_activated_for_user($user_id);
                
                if ($tfa_enabled) {
                    // Render the 2FA shortcode output
                    $tfa_html = do_shortcode('[twofactor_user_settings]');
                }
            }
        }

        // Get user's TFA status
        $user_tfa_activated = false;
        if ($tfa_available && isset($simba_tfa)) {
            $user_tfa_activated = $simba_tfa->is_activated_by_user($user_id);
        }

        return [
            'success' => true,
            'tfa_available' => $tfa_available,
            'tfa_enabled_for_user' => $tfa_enabled,
            'tfa_activated' => $user_tfa_activated,
            'html' => $tfa_html,
            'user_id' => $user_id,
            'debug' => [
                'tfa_class_exists' => $tfa_available,
                'tfa_instance_exists' => isset($simba_tfa),
            ],
        ];
    }

    /**
     * Save TFA settings via AJAX.
     * Handles enable/disable of TFA for the current user.
     *
     * @param \WP_REST_Request $request
     * @return array|\WP_Error
     */
    public function save_tfa_settings($request)
    {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();

        if (!class_exists('Simba_Two_Factor_Authentication_1')) {
            return new \WP_Error(
                'tfa_not_available',
                'Two-factor authentication plugin is not available.',
                ['status' => 400]
            );
        }

        global $simba_tfa;
        if (!isset($simba_tfa) || !is_object($simba_tfa)) {
            return new \WP_Error(
                'tfa_not_initialized',
                'Two-factor authentication plugin is not initialized.',
                ['status' => 400]
            );
        }

        // Handle TFA enable/disable
        if (isset($params['tfa_enable_tfa'])) {
            $enable = $params['tfa_enable_tfa'] ? 'true' : 'false';

            // If enabling, verify the current code
            if ($params['tfa_enable_tfa'] && isset($params['tfa_enable_current'])) {
                $totp_controller = $simba_tfa->get_controller('totp');
                $code_valid = $totp_controller->check_code_for_user($user_id, $params['tfa_enable_current'], false);

                if (!$code_valid) {
                    return new \WP_Error(
                        'code_invalid',
                        'The TFA code you entered was incorrect.',
                        ['status' => 400]
                    );
                }
            }

            $simba_tfa->change_tfa_enabled_status($user_id, $enable);

            return [
                'success' => true,
                'message' => $params['tfa_enable_tfa']
                    ? 'Two-factor authentication has been enabled.'
                    : 'Two-factor authentication has been disabled.',
                'tfa_activated' => (bool) $params['tfa_enable_tfa'],
            ];
        }

        // Handle algorithm change
        if (isset($params['tfa_algorithm_type'])) {
            $totp_controller = $simba_tfa->get_controller('totp');
            $totp_controller->changeUserAlgorithmTo($user_id, $params['tfa_algorithm_type']);

            return [
                'success' => true,
                'message' => 'Algorithm type has been updated.',
            ];
        }

        return new \WP_Error(
            'invalid_request',
            'No valid settings provided.',
            ['status' => 400]
        );
    }
}
