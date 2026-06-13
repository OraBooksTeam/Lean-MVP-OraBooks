<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'OraBooks_Email_Queue' ) ) {
    class OraBooks_Email_Queue {
        
        private static $instance = null;
        
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        public function __construct() {
            add_action( 'orabooks_email_cron', [ $this, 'process_queue' ] );
            add_action( 'orabooks_daily_cron', [ $this, 'send_daily_digest' ] );
        }
        
        /**
         * Queue an email for sending
         */
        public static function queue( $to, $subject, $message, $template = null, $priority = 5, $scheduled_at = null, $variables = [] ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            $instance = self::get_instance();
            
            // Process template if provided
            if ( $template ) {
                $message = $instance->process_template( $template, $variables );
            }
            
            $data = [
                'to_email' => sanitize_email( $to ),
                'subject' => sanitize_text_field( $subject ),
                'message' => wp_kses_post( $message ),
                'template' => $template ? sanitize_text_field( $template ) : null,
                'priority' => intval( $priority ),
                'status' => 'pending',
                'attempts' => 0,
                'scheduled_at' => $scheduled_at ?: current_time( 'mysql' ),
                'created_at' => current_time( 'mysql' )
            ];
            
            $table_name = isset($wpdb->orabooks_email_queue) ? $wpdb->orabooks_email_queue : $wpdb->prefix . 'orabooks_email_queue';
            $wpdb->insert( $table_name, $data );
            
            return $wpdb->insert_id;
        }
        
        /**
         * Process email queue
         */
        public function process_queue( $batch_size = 50 ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            $table_name = isset($wpdb->orabooks_email_queue) ? $wpdb->orabooks_email_queue : $wpdb->prefix . 'orabooks_email_queue';
            $emails = $wpdb->get_results( $wpdb->prepare( "
                SELECT * FROM $table_name
                WHERE status = 'pending'
                AND scheduled_at <= NOW()
                AND attempts < 3
                ORDER BY priority DESC, scheduled_at ASC
                LIMIT %d
            ", $batch_size ) );
            
            foreach ( $emails as $email ) {
                $this->send_email( $email );
            }
        }
        
        /**
         * Send individual email
         */
        private function send_email( $email ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Get system domain-based email
            $system_domain = parse_url(network_site_url(), PHP_URL_HOST);
            $from_email = 'noreply@' . $system_domain;
            
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_option( 'blogname' ) . ' <' . $from_email . '>'
            ];
            
            $sent = wp_mail( $email->to_email, $email->subject, $email->message, $headers );
            
            $queue_table = isset($wpdb->orabooks_email_queue) ? $wpdb->orabooks_email_queue : $wpdb->prefix . 'orabooks_email_queue';
            if ( $sent ) {
                // Mark as sent
                $wpdb->update(
                    $queue_table,
                    [
                        'status' => 'sent',
                        'sent_at' => current_time( 'mysql' )
                    ],
                    [ 'id' => $email->id ]
                );
                
                // Log successful send
                orabooks_log_info( 'email_sent', 'Email sent successfully', [
                    'email_id' => $email->id,
                    'to' => $email->to_email,
                    'subject' => $email->subject,
                    'template' => $email->template
                ]);
                
            } else {
                // Increment attempts and update status
                $attempts = $email->attempts + 1;
                $status = $attempts >= 3 ? 'failed' : 'pending';
                
                $wpdb->update(
                    $queue_table,
                    [
                        'attempts' => $attempts,
                        'status' => $status,
                        'last_error' => 'wp_mail() returned false'
                    ],
                    [ 'id' => $email->id ]
                );
                
                // Log failed send
                orabooks_log_warning( 'email_failed', 'Email send failed', [
                    'email_id' => $email->id,
                    'to' => $email->to_email,
                    'subject' => $email->subject,
                    'attempt' => $attempts
                ]);
            }
        }
        
        /**
         * Process email template
         */
        private function process_template( $template, $variables = [] ) {
            $templates = $this->get_email_templates();
            $template_content = $templates[$template] ?? '';
            
            // Replace variables
            foreach ( $variables as $key => $value ) {
                $template_content = str_replace( '{' . $key . '}', (string) $value, (string) $template_content );
            }
            
            return $template_content;
        }
        
        /**
         * Get email templates
         */
        private function get_email_templates() {
            $site_name = get_bloginfo( 'name' );
            $site_url = home_url();
            
            return [
                'welcome' => "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <div style='background: #43a62d; color: white; padding: 20px; text-align: center;'>
                            <h1 style='margin: 0; font-size: 24px;'>Welcome to {$site_name}!</h1>
                        </div>
                        <div style='padding: 30px; background: #f9f9f9;'>
                            <h2 style='color: #333; margin-top: 0;'>Hi {user_name},</h2>
                            <p style='color: #666; line-height: 1.6;'>Thank you for joining {$site_name}! We're excited to have you on board.</p>
                            <p style='color: #666; line-height: 1.6;'>Your {plan_name} subscription is now active and you can start using all the features right away.</p>
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='{dashboard_url}' style='background: #43a62d; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Go to Dashboard</a>
                            </div>
                            <p style='color: #666; line-height: 1.6;'>If you have any questions, please don't hesitate to contact our support team.</p>
                        </div>
                        <div style='background: #333; color: white; padding: 20px; text-align: center; font-size: 12px;'>
                            <p style='margin: 0;'>&copy; " . date('Y') . " {$site_name}. All rights reserved.</p>
                            <p style='margin: 10px 0 0 0;'>
                                <a href='{$site_url}/privacy' style='color: white; text-decoration: none;'>Privacy Policy</a> | 
                                <a href='{$site_url}/terms' style='color: white; text-decoration: none;'>Terms of Service</a>
                            </p>
                        </div>
                    </div>
                ",
                
                'payment_success' => "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <div style='background: #28a745; color: white; padding: 20px; text-align: center;'>
                            <h1 style='margin: 0; font-size: 24px;'>Payment Successful!</h1>
                        </div>
                        <div style='padding: 30px; background: #f9f9f9;'>
                            <h2 style='color: #333; margin-top: 0;'>Hi {user_name},</h2>
                            <p style='color: #666; line-height: 1.6;'>Great news! Your payment has been processed successfully.</p>
                            <div style='background: white; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                                <p style='margin: 0;'><strong>Order ID:</strong> {order_id}</p>
                                <p style='margin: 10px 0 0 0;'><strong>Amount:</strong> {currency}{amount}</p>
                                <p style='margin: 10px 0 0 0;'><strong>Plan:</strong> {plan_name}</p>
                                <p style='margin: 10px 0 0 0;'><strong>Date:</strong> {payment_date}</p>
                            </div>
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='{invoice_url}' style='background: #43a62d; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Invoice</a>
                            </div>
                        </div>
                        <div style='background: #333; color: white; padding: 20px; text-align: center; font-size: 12px;'>
                            <p style='margin: 0;'>&copy; " . date('Y') . " {$site_name}. All rights reserved.</p>
                        </div>
                    </div>
                ",
                
                'payment_failed' => "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <div style='background: #dc3545; color: white; padding: 20px; text-align: center;'>
                            <h1 style='margin: 0; font-size: 24px;'>Payment Failed</h1>
                        </div>
                        <div style='padding: 30px; background: #f9f9f9;'>
                            <h2 style='color: #333; margin-top: 0;'>Hi {user_name},</h2>
                            <p style='color: #666; line-height: 1.6;'>We were unable to process your payment for the {plan_name} subscription.</p>
                            <div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                                <p style='margin: 0; color: #856404;'><strong>Attempt:</strong> {attempt} of 3</p>
                                <p style='margin: 10px 0 0 0; color: #856404;'><strong>Amount:</strong> {currency}{amount}</p>
                                <p style='margin: 10px 0 0 0; color: #856404;'><strong>Next Retry:</strong> {retry_date}</p>
                            </div>
                            <p style='color: #666; line-height: 1.6;'>Please update your payment method or contact your bank to resolve this issue.</p>
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='{update_payment_url}' style='background: #43a62d; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Update Payment Method</a>
                            </div>
                        </div>
                        <div style='background: #333; color: white; padding: 20px; text-align: center; font-size: 12px;'>
                            <p style='margin: 0;'>&copy; " . date('Y') . " {$site_name}. All rights reserved.</p>
                        </div>
                    </div>
                ",
                
                'renewal_reminder' => "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <div style='background: #ffc107; color: #333; padding: 20px; text-align: center;'>
                            <h1 style='margin: 0; font-size: 24px;'>Subscription Renewal Reminder</h1>
                        </div>
                        <div style='padding: 30px; background: #f9f9f9;'>
                            <h2 style='color: #333; margin-top: 0;'>Hi {user_name},</h2>
                            <p style='color: #666; line-height: 1.6;'>Your {plan_name} subscription will expire in {days_until_expiry} days.</p>
                            <div style='background: white; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                                <p style='margin: 0;'><strong>Plan:</strong> {plan_name}</p>
                                <p style='margin: 10px 0 0 0;'><strong>Expiry Date:</strong> {expiry_date}</p>
                                <p style='margin: 10px 0 0 0;'><strong>Days Remaining:</strong> {days_until_expiry}</p>
                            </div>
                            <p style='color: #666; line-height: 1.6;'>To continue enjoying our services without interruption, please renew your subscription.</p>
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='{renew_url}' style='background: #43a62d; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Renew Now</a>
                            </div>
                        </div>
                        <div style='background: #333; color: white; padding: 20px; text-align: center; font-size: 12px;'>
                            <p style='margin: 0;'>&copy; " . date('Y') . " {$site_name}. All rights reserved.</p>
                        </div>
                    </div>
                ",
                
                'subscription_expired' => "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <div style='background: #dc3545; color: white; padding: 20px; text-align: center;'>
                            <h1 style='margin: 0; font-size: 24px;'>Subscription Expired</h1>
                        </div>
                        <div style='padding: 30px; background: #f9f9f9;'>
                            <h2 style='color: #333; margin-top: 0;'>Hi {user_name},</h2>
                            <p style='color: #666; line-height: 1.6;'>Your {plan_name} subscription expired on {expiry_date}.</p>
                            <p style='color: #666; line-height: 1.6;'>We hope you enjoyed using our services! You can renew your subscription at any time to continue accessing all features.</p>
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='{renew_url}' style='background: #43a62d; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Renew Subscription</a>
                            </div>
                        </div>
                        <div style='background: #333; color: white; padding: 20px; text-align: center; font-size: 12px;'>
                            <p style='margin: 0;'>&copy; " . date('Y') . " {$site_name}. All rights reserved.</p>
                        </div>
                    </div>
                ",
                
                'feature_unlocked' => "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <div style='background: #17a2b8; color: white; padding: 20px; text-align: center;'>
                            <h1 style='margin: 0; font-size: 24px;'>New Feature Unlocked!</h1>
                        </div>
                        <div style='padding: 30px; background: #f9f9f9;'>
                            <h2 style='color: #333; margin-top: 0;'>Hi {user_name},</h2>
                            <p style='color: #666; line-height: 1.6;'>Great news! You now have access to a new feature.</p>
                            <div style='background: white; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                                <h3 style='margin: 0 0 10px 0; color: #43a62d;'>{feature_name}</h3>
                                <p style='margin: 0; color: #666;'>{feature_description}</p>
                            </div>
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='{feature_url}' style='background: #43a62d; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Try It Now</a>
                            </div>
                        </div>
                        <div style='background: #333; color: white; padding: 20px; text-align: center; font-size: 12px;'>
                            <p style='margin: 0;'>&copy; " . date('Y') . " {$site_name}. All rights reserved.</p>
                        </div>
                    </div>
                ",
                
                'plan_upgrade' => "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <div style='background: #6f42c1; color: white; padding: 20px; text-align: center;'>
                            <h1 style='margin: 0; font-size: 24px;'>Plan Upgraded!</h1>
                        </div>
                        <div style='padding: 30px; background: #f9f9f9;'>
                            <h2 style='color: #333; margin-top: 0;'>Hi {user_name},</h2>
                            <p style='color: #666; line-height: 1.6;'>Congratulations! You've successfully upgraded to the {new_plan_name} plan.</p>
                            <div style='background: white; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                                <p style='margin: 0;'><strong>Previous Plan:</strong> {old_plan_name}</p>
                                <p style='margin: 10px 0 0 0;'><strong>New Plan:</strong> {new_plan_name}</p>
                                <p style='margin: 10px 0 0 0;'><strong>Price:</strong> {currency}{new_price}/{billing_period}</p>
                            </div>
                            <p style='color: #666; line-height: 1.6;'>You now have access to all the features included in your new plan.</p>
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='{dashboard_url}' style='background: #43a62d; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Go to Dashboard</a>
                            </div>
                        </div>
                        <div style='background: #333; color: white; padding: 20px; text-align: center; font-size: 12px;'>
                            <p style='margin: 0;'>&copy; " . date('Y') . " {$site_name}. All rights reserved.</p>
                        </div>
                    </div>
                "
            ];
        }
        
        /**
         * Send daily digest email
         */
        public function send_daily_digest() {
            $admin_email = get_option( 'admin_email' );
            $site_name = get_bloginfo( 'name' );
            
            // Get daily statistics
            $stats = $this->get_daily_stats();
            
            $subject = "Daily Digest - {$site_name} - " . date( 'Y-m-d' );
            
            $message = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: #43a62d; color: white; padding: 20px; text-align: center;'>
                        <h1 style='margin: 0; font-size: 24px;'>Daily Digest</h1>
                        <p style='margin: 10px 0 0 0; font-size: 16px;'>{$site_name} - " . date( 'F j, Y' ) . "</p>
                    </div>
                    <div style='padding: 30px; background: #f9f9f9;'>
                        <h2 style='color: #333; margin-top: 0;'>Today's Overview</h2>
                        
                        <div style='display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;'>
                            <div style='background: white; padding: 15px; border-radius: 5px; text-align: center;'>
                                <h3 style='margin: 0; color: #43a62d; font-size: 24px;'>{$stats['new_signups']}</h3>
                                <p style='margin: 5px 0 0 0; color: #666;'>New Signups</p>
                            </div>
                            <div style='background: white; padding: 15px; border-radius: 5px; text-align: center;'>
                                <h3 style='margin: 0; color: #43a62d; font-size: 24px;'>" . $this->format_currency( $stats['revenue'] ) . "</h3>
                                <p style='margin: 5px 0 0 0; color: #666;'>Revenue</p>
                            </div>
                            <div style='background: white; padding: 15px; border-radius: 5px; text-align: center;'>
                                <h3 style='margin: 0; color: #43a62d; font-size: 24px;'>{$stats['active_subscriptions']}</h3>
                                <p style='margin: 5px 0 0 0; color: #666;'>Active Subscriptions</p>
                            </div>
                            <div style='background: white; padding: 15px; border-radius: 5px; text-align: center;'>
                                <h3 style='margin: 0; color: #43a62d; font-size: 24px;'>{$stats['payment_success_rate']}%</h3>
                                <p style='margin: 5px 0 0 0; color: #666;'>Payment Success Rate</p>
                            </div>
                        </div>
                        
                        <h3 style='color: #333; margin: 30px 0 15px 0;'>Recent Activity</h3>
                        <div style='background: white; padding: 15px; border-radius: 5px;'>
                            {$stats['recent_activity_html']}
                        </div>
                        
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='" . admin_url( 'admin.php?page=orabooks-analytics' ) . "' style='background: #43a62d; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Full Analytics</a>
                        </div>
                    </div>
                    <div style='background: #333; color: white; padding: 20px; text-align: center; font-size: 12px;'>
                        <p style='margin: 0;'>&copy; " . date('Y') . " {$site_name}. All rights reserved.</p>
                    </div>
                </div>
            ";
            
            wp_mail( $admin_email, $subject, $message, [ 'Content-Type: text/html; charset=UTF-8' ] );
        }
        
        /**
         * Get daily statistics
         */
        private function get_daily_stats() {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            $stats = [];
            
            // New signups today
            $stats['new_signups'] = $wpdb->get_var( "
                SELECT COUNT(*) FROM {$wpdb->orabooks_subscriptions}
                WHERE DATE(created_at) = CURDATE()
            " ) ?: 0;
            
            // Revenue today
            $stats['revenue'] = $wpdb->get_var( "
                SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->orabooks_orders}
                WHERE status = 'completed'
                AND DATE(created_at) = CURDATE()
            " ) ?: 0;
            
            // Active subscriptions
            $stats['active_subscriptions'] = $wpdb->get_var( "
                SELECT COUNT(*) FROM {$wpdb->orabooks_subscriptions}
                WHERE status = 'active'
            " ) ?: 0;
            
            // Payment success rate today
            $total_payments = $wpdb->get_var( "
                SELECT COUNT(*) FROM {$wpdb->orabooks_orders}
                WHERE DATE(created_at) = CURDATE()
            " ) ?: 1;
            
            $successful_payments = $wpdb->get_var( "
                SELECT COUNT(*) FROM {$wpdb->orabooks_orders}
                WHERE status = 'completed'
                AND DATE(created_at) = CURDATE()
            " ) ?: 0;
            
            $stats['payment_success_rate'] = round( ( $successful_payments / $total_payments ) * 100, 1 );
            
            // Recent activity HTML
            $recent_activity = $wpdb->get_results( "
                SELECT 
                    o.created_at,
                    'payment' as type,
                    u.display_name as user_name,
                    o.amount,
                    o.status
                FROM {$wpdb->orabooks_orders} o
                LEFT JOIN {$wpdb->users} u ON o.user_id = u.ID
                WHERE DATE(o.created_at) = CURDATE()
                ORDER BY o.created_at DESC
                LIMIT 5
            " );
            
            $activity_html = '';
            foreach ( $recent_activity as $activity ) {
                $status_class = $activity->status === 'completed' ? 'color: #28a745;' : 'color: #dc3545;';
                $activity_html .= sprintf(
                    '<p style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 3px;">
                        <strong>%s</strong> - %s - 
                        <span style="%s">%s</span> - 
                        %s
                    </p>',
                    date( 'H:i', strtotime( $activity->created_at ) ),
                    $activity->user_name ?: 'Guest',
                    $status_class,
                    ucfirst( $activity->status ),
                    $activity->amount ? $this->format_currency( $activity->amount ) : '-'
                );
            }
            
            $stats['recent_activity_html'] = $activity_html ?: '<p style="color: #666;">No recent activity today.</p>';
            
            return $stats;
        }
        
        /**
         * Format currency
         */
        private function format_currency( $amount ) {
            $currency_symbol = get_option( 'orabooks_currency_symbol', '৳' );
            return $currency_symbol . number_format( $amount, 2 );
        }
        
        /**
         * Get queue statistics
         */
        public static function get_queue_stats() {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            $stats = [];
            
            $stats['pending'] = $wpdb->get_var( "
                SELECT COUNT(*) FROM {$wpdb->prefix}orabooks_email_queue
                WHERE status = 'pending'
            " ) ?: 0;
            
            $stats['sent_today'] = $wpdb->get_var( "
                SELECT COUNT(*) FROM {$wpdb->prefix}orabooks_email_queue
                WHERE status = 'sent'
                AND DATE(sent_at) = CURDATE()
            " ) ?: 0;
            
            $stats['failed_today'] = $wpdb->get_var( "
                SELECT COUNT(*) FROM {$wpdb->prefix}orabooks_email_queue
                WHERE status = 'failed'
                AND DATE(created_at) = CURDATE()
            " ) ?: 0;
            
            return $stats;
        }
        
        /**
         * Cleanup old emails
         */
        public static function cleanup_old_emails( $days = 30 ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            return $wpdb->query( $wpdb->prepare( "
                DELETE FROM {$wpdb->prefix}orabooks_email_queue
                WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
            ", $days ) );
        }
    }
}

// Helper functions for global access
function orabooks_queue_email( $to, $subject, $message, $template = null, $priority = 5, $scheduled_at = null, $variables = [] ) {
    return OraBooks_Email_Queue::queue( $to, $subject, $message, $template, $priority, $scheduled_at, $variables );
}

// Initialize cron scheduling on plugin init
add_action( 'init', 'orabooks_email_queue_init_cron' );
function orabooks_email_queue_init_cron() {
    if ( ! wp_next_scheduled( 'orabooks_email_cron' ) ) {
        wp_schedule_event( time(), 'orabooks_email_interval', 'orabooks_email_cron' );
    }
}

// Add custom cron interval for email processing
add_filter( 'cron_schedules', 'orabooks_email_queue_cron_interval' );
function orabooks_email_queue_cron_interval( $schedules ) {
    $schedules['orabooks_email_interval'] = [
        'interval' => 5 * MINUTE_IN_SECONDS,
        'display' => 'Every 5 minutes'
    ];
    return $schedules;
}

// Cleanup old emails weekly
add_action( 'orabooks_weekly_cron', 'orabooks_email_queue_weekly_cleanup' );
function orabooks_email_queue_weekly_cleanup() {
    OraBooks_Email_Queue::cleanup_old_emails( 30 );
}
