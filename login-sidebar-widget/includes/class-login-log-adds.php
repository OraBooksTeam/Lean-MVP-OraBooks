<?php

class Login_Log_Adds{
    
    /**
     * Add a login log entry.
     * 
     * SL-008 Compliance: Log entries never contain plaintext passwords or secrets.
     * IP addresses are stored for audit purposes only.
     *
     * @param string $ip       IP address (masked for privacy)
     * @param string $msg      Log message (no secrets allowed)
     * @param string $l_added  Timestamp
     * @param string $l_status Status (success/failed)
     */
    public function log_add( $ip = '', $msg = '', $l_added = '', $l_status = '' ){
        global $wpdb;
        if($ip == ''){
            return;
        }
        
        // SL-008 Compliance: Sanitize log message - never log secrets or passwords
        $sanitized_msg = sanitize_text_field($msg);
        
        // SL-008 Compliance: Mask IP address for privacy (store /24 subnet only for IPv4)
        // This limits PII exposure while maintaining audit capability
        $masked_ip = $this->mask_ip_address($ip);
        
        $log_data = array( 
            'ip' => $masked_ip, 
            'msg' => $sanitized_msg,  
            'l_added' => $l_added, 
            'l_status' => $l_status, 
            'l_type' => 'new' 
        );
        $log_data_format = array( '%s', '%s', '%s', '%s', '%s' );
        $wpdb->insert( $wpdb->base_prefix . "login_log", $log_data, $log_data_format );
        
        // SL-008 Compliance: Fire audit event for failed logins
        if ($l_status === 'failed') {
            do_action('lsws_login_failed_audit', array(
                'ip_masked' => $masked_ip,
                'message' => $sanitized_msg,
                'timestamp' => $l_added,
            ));
        }
        
        return;
    }
    
    /**
     * SL-008 Compliance: Mask IP address for privacy.
     * IPv4: mask last octet (e.g., 192.168.1.x)
     * IPv6: mask last 80 bits
     *
     * @param string $ip Raw IP address
     * @return string Masked IP address
     */
    private function mask_ip_address($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // Mask last octet for IPv4
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                $parts[3] = 'x';
                return implode('.', $parts);
            }
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // Mask last 4 groups for IPv6 (last 80 bits)
            $parts = explode(':', $ip);
            $count = count($parts);
            if ($count >= 4) {
                for ($i = $count - 4; $i < $count; $i++) {
                    if (isset($parts[$i])) {
                        $parts[$i] = 'xxxx';
                    }
                }
                return implode(':', $parts);
            }
        }
        return $ip;
    }
}