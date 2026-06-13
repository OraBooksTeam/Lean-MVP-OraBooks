<?php

if ( ! class_exists( 'OraBooks_Error_Logger' ) ) {
    class OraBooks_Error_Logger {
        
        private static $instance = null;
        private $log_table;
        private $enable_file_logging = true;
        private $enable_database_logging = true;
        private $enable_external_monitoring = false;
        
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        public function __construct() {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            $this->log_table = $wpdb->prefix . 'orabooks_error_log';
            $this->init_settings();
            $this->create_log_table();
        }
        
        private function init_settings() {
            $this->enable_file_logging = defined( 'ORABOOKS_ENABLE_FILE_LOGGING' ) ? ORABOOKS_ENABLE_FILE_LOGGING : true;
            $this->enable_database_logging = defined( 'ORABOOKS_ENABLE_DB_LOGGING' ) ? ORABOOKS_ENABLE_DB_LOGGING : true;
            $this->enable_external_monitoring = defined( 'ORABOOKS_ENABLE_EXTERNAL_MONITORING' ) ? ORABOOKS_ENABLE_EXTERNAL_MONITORING : false;
        }
        
        private function create_log_table() {
            if ( ! $this->enable_database_logging ) {
                return;
            }
            
            global $wpdb;
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE IF NOT EXISTS {$this->log_table} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                error_type varchar(50) NOT NULL,
                severity enum('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                message text NOT NULL,
                context longtext NULL,
                user_id bigint(20) NULL,
                ip_address varchar(45) NULL,
                user_agent text NULL,
                request_uri varchar(500) NULL,
                referer varchar(500) NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                resolved_at datetime NULL,
                resolved_by bigint(20) NULL,
                PRIMARY KEY (id),
                KEY idx_error_type (error_type),
                KEY idx_severity (severity),
                KEY idx_created_at (created_at),
                KEY idx_user_id (user_id),
                KEY idx_resolved (resolved_at)
            ) $charset_collate;";
            
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        }
        
        public static function log_error( $type, $message, $context = [], $severity = 'medium' ) {
            $instance = self::get_instance();
            $instance->_log_error( $type, $message, $context, $severity );
        }
        
        public static function log_critical( $type, $message, $context = [] ) {
            self::log_error( $type, $message, $context, 'critical' );
        }
        
        public static function log_warning( $type, $message, $context = [] ) {
            self::log_error( $type, $message, $context, 'medium' );
        }
        
        public static function log_info( $type, $message, $context = [] ) {
            self::log_error( $type, $message, $context, 'low' );
        }
        
        private function _log_error( $type, $message, $context = [], $severity = 'medium' ) {
            $data = [
                'error_type' => sanitize_text_field( $type ),
                'severity' => in_array( $severity, ['low', 'medium', 'high', 'critical'] ) ? $severity : 'medium',
                'message' => substr( sanitize_textarea_field( $message ), 0, 1000 ),
                'context' => wp_json_encode( $context ),
                'user_id' => get_current_user_id(),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 500 ),
                'request_uri' => substr( sanitize_text_field( $_SERVER['REQUEST_URI'] ?? '' ), 0, 500 ),
                'referer' => substr( sanitize_text_field( $_SERVER['HTTP_REFERER'] ?? '' ), 0, 500 ),
                'created_at' => current_time( 'mysql' )
            ];
            
            // Log to database
            if ( $this->enable_database_logging ) {
                $this->log_to_database( $data );
            }
            
            // Log to file
            if ( $this->enable_file_logging ) {
                $this->log_to_file( $data );
            }
            
            // Send to external monitoring
            if ( $this->enable_external_monitoring ) {
                $this->send_to_external_monitoring( $data );
            }
            
            // Send immediate alerts for critical errors
            if ( $severity === 'critical' ) {
                $this->send_critical_alert( $data );
            }
        }
        
        private function log_to_database( $data ) {
            global $wpdb;
            
            try {
                $wpdb->insert( $this->log_table, $data );
            } catch ( Exception $e ) {
                error_log( 'OraBooks Error Logger: Failed to log to database: ' . $e->getMessage() );
            }
        }
        
        private function log_to_file( $data ) {
            $log_dir = WP_CONTENT_DIR . '/orabooks-logs';
            
            // Create log directory if it doesn't exist
            if ( ! file_exists( $log_dir ) ) {
                wp_mkdir_p( $log_dir );
            }
            
            $log_file = $log_dir . '/error-' . date( 'Y-m-d' ) . '.log';
            $timestamp = current_time( 'Y-m-d H:i:s' );
            
            $log_entry = sprintf(
                "[%s] %s [%s] %s | User: %d | IP: %s | %s\n",
                $timestamp,
                strtoupper( $data['severity'] ),
                $data['error_type'],
                $data['message'],
                $data['user_id'],
                $data['ip_address'],
                $data['context']
            );
            
            file_put_contents( $log_file, $log_entry, FILE_APPEND | LOCK_EX );
        }
        
        private function send_to_external_monitoring( $data ) {
            // Integration with external monitoring services
            // This is a placeholder for services like DataDog, New Relic, etc.
            
            if ( defined( 'ORABOOKS_DATADOG_API_KEY' ) && class_exists( 'Datadog' ) ) {
                try {
                    $datadog = new Datadog( ORABOOKS_DATADOG_API_KEY );
                    $datadog->event( $data['message'], [
                        'alert_type' => $data['severity'],
                        'tags' => [
                            'error_type:' . $data['error_type'],
                            'user_id:' . $data['user_id'],
                            'severity:' . $data['severity']
                        ]
                    ] );
                } catch ( Exception $e ) {
                    error_log( 'OraBooks Error Logger: Failed to send to DataDog: ' . $e->getMessage() );
                }
            }
        }
        
        private function send_critical_alert( $data ) {
            // Send email alert for critical errors
            $admin_email = get_option( 'admin_email' );
            $site_name = get_bloginfo( 'name' );
            
            $subject = sprintf( '[CRITICAL] %s - %s Error', $site_name, $data['error_type'] );
            
            $message = sprintf(
                "A critical error occurred on %s:\n\n" .
                "Type: %s\n" .
                "Message: %s\n" .
                "Severity: %s\n" .
                "User ID: %s\n" .
                "IP Address: %s\n" .
                "Time: %s\n" .
                "Context: %s\n\n" .
                "Please investigate immediately.",
                $site_name,
                $data['error_type'],
                $data['message'],
                $data['severity'],
                $data['user_id'] ?: 'Guest',
                $data['ip_address'],
                $data['created_at'],
                $data['context']
            );
            
            wp_mail( $admin_email, $subject, $message );
        }
        
        private function get_client_ip() {
            $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
            
            foreach ( $ip_keys as $key ) {
                if ( ! empty( $_SERVER[$key] ) ) {
                    $ips = explode( ',', $_SERVER[$key] );
                    $ip = trim( $ips[0] );
                    
                    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                        return $ip;
                    }
                }
            }
            
            return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        
        public static function get_error_logs( $args = [] ) {
            $instance = self::get_instance();
            return $instance->_get_error_logs( $args );
        }
        
        private function _get_error_logs( $args = [] ) {
            global $wpdb;
            
            $defaults = [
                'limit' => 50,
                'offset' => 0,
                'error_type' => null,
                'severity' => null,
                'user_id' => null,
                'date_from' => null,
                'date_to' => null,
                'resolved' => null
            ];
            
            $args = wp_parse_args( $args, $defaults );
            
            $where_conditions = ['1=1'];
            $where_values = [];
            
            if ( $args['error_type'] ) {
                $where_conditions[] = 'error_type = %s';
                $where_values[] = $args['error_type'];
            }
            
            if ( $args['severity'] ) {
                $where_conditions[] = 'severity = %s';
                $where_values[] = $args['severity'];
            }
            
            if ( $args['user_id'] ) {
                $where_conditions[] = 'user_id = %d';
                $where_values[] = $args['user_id'];
            }
            
            if ( $args['date_from'] ) {
                $where_conditions[] = 'created_at >= %s';
                $where_values[] = $args['date_from'];
            }
            
            if ( $args['date_to'] ) {
                $where_conditions[] = 'created_at <= %s';
                $where_values[] = $args['date_to'];
            }
            
            if ( $args['resolved'] !== null ) {
                if ( $args['resolved'] ) {
                    $where_conditions[] = 'resolved_at IS NOT NULL';
                } else {
                    $where_conditions[] = 'resolved_at IS NULL';
                }
            }
            
            $where_clause = implode( ' AND ', $where_conditions );
            $limit_clause = $wpdb->prepare( 'LIMIT %d OFFSET %d', $args['limit'], $args['offset'] );
            
            $sql = "SELECT * FROM {$this->log_table} 
                    WHERE {$where_clause} 
                    ORDER BY created_at DESC 
                    {$limit_clause}";
            
            if ( ! empty( $where_values ) ) {
                $sql = $wpdb->prepare( $sql, $where_values );
            }
            
            return $wpdb->get_results( $sql );
        }
        
        public static function get_error_stats() {
            $instance = self::get_instance();
            return $instance->_get_error_stats();
        }
        
        private function _get_error_stats() {
            global $wpdb;
            
            $stats = [];
            
            // Total errors
            $stats['total'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->log_table}" );
            
            // By severity
            $stats['by_severity'] = $wpdb->get_results( "
                SELECT severity, COUNT(*) as count 
                FROM {$this->log_table} 
                GROUP BY severity
            " );
            
            // By type (last 7 days)
            $stats['by_type'] = $wpdb->get_results( $wpdb->prepare( "
                SELECT error_type, COUNT(*) as count 
                FROM {$this->log_table} 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY error_type 
                ORDER BY count DESC 
                LIMIT 10
            " ) );
            
            // Recent critical errors
            $stats['recent_critical'] = $wpdb->get_var( $wpdb->prepare( "
                SELECT COUNT(*) FROM {$this->log_table} 
                WHERE severity = 'critical' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            " ) );
            
            return $stats;
        }
        
        public static function cleanup_old_logs( $days = 30 ) {
            $instance = self::get_instance();
            return $instance->_cleanup_old_logs( $days );
        }
        
        private function _cleanup_old_logs( $days = 30 ) {
            global $wpdb;
            
            $deleted = $wpdb->query( $wpdb->prepare( "
                DELETE FROM {$this->log_table} 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
                AND resolved_at IS NOT NULL
            ", $days ) );
            
            // Also clean up old log files
            $log_dir = WP_CONTENT_DIR . '/orabooks-logs';
            if ( is_dir( $log_dir ) ) {
                $files = glob( $log_dir . '/error-*.log' );
                foreach ( $files as $file ) {
                    if ( filemtime( $file ) < strtotime( "-{$days} days" ) ) {
                        unlink( $file );
                    }
                }
            }
            
            return $deleted;
        }
    }
}

// Helper functions for global access
function orabooks_log_error( $type, $message, $context = [], $severity = 'medium' ) {
    OraBooks_Error_Logger::log_error( $type, $message, $context, $severity );
}

function orabooks_log_critical( $type, $message, $context = [] ) {
    OraBooks_Error_Logger::log_critical( $type, $message, $context );
}

function orabooks_log_warning( $type, $message, $context = [] ) {
    OraBooks_Error_Logger::log_warning( $type, $message, $context );
}

function orabooks_log_info( $type, $message, $context = [] ) {
    OraBooks_Error_Logger::log_info( $type, $message, $context );
}

// Set up automatic cleanup
add_action( 'orabooks_daily_cron', function() {
    OraBooks_Error_Logger::cleanup_old_logs( 30 );
});

// Log payment failures automatically
add_action( 'orabooks_payment_failed', function( $order_id, $error_message ) {
    orabooks_log_critical( 'payment_failure', "Payment failed for order #{$order_id}", [
        'order_id' => $order_id,
        'error_message' => $error_message
    ]);
}, 10, 2 );

// Log subscription issues
add_action( 'orabooks_subscription_error', function( $subscription_id, $error ) {
    orabooks_log_error( 'subscription_error', "Subscription error: {$error}", [
        'subscription_id' => $subscription_id,
        'error' => $error
    ]);
}, 10, 2 );
