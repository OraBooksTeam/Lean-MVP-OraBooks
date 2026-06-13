<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'OraBooks_Dunning_Manager' ) ) {
    class OraBooks_Dunning_Manager {
        
        private static $instance = null;
        
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        public function __construct() {
            add_action( 'orabooks_hourly_cron', [ $this, 'process_dunning' ] );
            add_action( 'orabooks_payment_failed', [ $this, 'handle_payment_failure' ], 10, 2 );
            add_action( 'orabooks_subscription_renewal_failed', [ $this, 'handle_renewal_failure' ], 10, 2 );
        }
        
        /**
         * Process dunning workflow
         */
        public function process_dunning() {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Find subscriptions that need dunning processing
            $subscriptions = $wpdb->get_results( $wpdb->prepare( "
                SELECT s.*, u.user_email, u.display_name, l.name as level_name, l.price, l.currency_symbol
                FROM {$wpdb->orabooks_subscriptions} s
                JOIN {$wpdb->users} u ON s.user_id = u.ID
                JOIN {$wpdb->orabooks_levels} l ON s.level_id = l.id
                WHERE s.status IN (%s, %s)
                AND (
                    (s.last_renewal_attempt IS NOT NULL AND s.last_renewal_attempt < DATE_SUB(NOW(), INTERVAL 24 HOUR))
                    OR
                    (s.ends_at <= DATE_ADD(NOW(), INTERVAL 7 DAY) AND s.auto_renew = %d)
                )
                AND s.renewal_attempts < %d
            ", 'active', 'grace_period', 1, 3 ) );
            
            foreach ( $subscriptions as $subscription ) {
                $this->process_subscription_dunning( $subscription );
            }
        }
        
        /**
         * Process individual subscription dunning
         */
        private function process_subscription_dunning( $subscription ) {
            $attempt = $subscription->renewal_attempts + 1;
            
            switch ( $attempt ) {
                case 1:
                    $this->send_first_dunning_email( $subscription );
                    break;
                    
                case 2:
                    $this->send_second_dunning_email( $subscription );
                    break;
                    
                case 3:
                    $this->send_final_dunning_email( $subscription );
                    $this->apply_grace_period( $subscription );
                    break;
            }
        }
        
        /**
         * Handle payment failure
         */
        public function handle_payment_failure( $order_id, $error_message ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Get subscription related to this order
            $subscription = $wpdb->get_row( $wpdb->prepare( "
                SELECT s.*, u.user_email, u.display_name, l.name as level_name, l.price, l.currency_symbol
                FROM {$wpdb->orabooks_orders} o
                JOIN {$wpdb->orabooks_subscriptions} s ON o.user_id = s.user_id AND o.level_id = s.level_id
                JOIN {$wpdb->users} u ON s.user_id = u.ID
                JOIN {$wpdb->orabooks_levels} l ON s.level_id = l.id
                WHERE o.id = %d
                AND s.status = 'active'
                ORDER BY s.id DESC
                LIMIT 1
            ", $order_id ) );
            
            if ( $subscription ) {
                $this->handle_payment_failure_for_subscription( $subscription, $error_message );
            }
        }
        
        /**
         * Handle renewal failure
         */
        public function handle_renewal_failure( $subscription_id, $error_message ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            $subscription = $wpdb->get_row( $wpdb->prepare( "
                SELECT s.*, u.user_email, u.display_name, l.name as level_name, l.price, l.currency_symbol
                FROM {$wpdb->orabooks_subscriptions} s
                JOIN {$wpdb->users} u ON s.user_id = u.ID
                JOIN {$wpdb->orabooks_levels} l ON s.level_id = l.id
                WHERE s.id = %d
            ", $subscription_id ) );
            
            if ( $subscription ) {
                $this->handle_payment_failure_for_subscription( $subscription, $error_message );
            }
        }
        
        /**
         * Handle payment failure for subscription
         */
        private function handle_payment_failure_for_subscription( $subscription, $error_message ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Update renewal attempt
            $new_attempts = $subscription->renewal_attempts + 1;
            $wpdb->update(
                $wpdb->orabooks_subscriptions,
                [
                    'renewal_attempts' => $new_attempts,
                    'last_renewal_attempt' => current_time( 'mysql' )
                ],
                [ 'id' => $subscription->id ]
            );
            
            // Refresh subscription object to get updated attempt count
            $subscription->renewal_attempts = $new_attempts;
            $subscription->last_renewal_attempt = current_time( 'mysql' );
            
            // Process dunning based on attempt count
            $this->process_subscription_dunning( $subscription );
            
            // Log the failure
            orabooks_log_warning( 'dunning_payment_failed', 'Payment failed in dunning process', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'attempt' => $subscription->renewal_attempts + 1,
                'error' => $error_message
            ]);
        }
        
        /**
         * Send first dunning email
         */
        private function send_first_dunning_email( $subscription ) {
            $subject = sprintf( 'Payment Issue - %s Subscription', $subscription->level_name );
            
            $message = $this->get_email_template( 'first_dunning', [
                'user_name' => $subscription->display_name,
                'level_name' => $subscription->level_name,
                'amount' => $subscription->price,
                'currency' => $subscription->currency_symbol,
                'error_message' => 'We were unable to process your payment',
                'update_payment_url' => $this->get_update_payment_url(),
                'retry_date' => date( 'Y-m-d H:i', strtotime( '+24 hours' ) )
            ]);
            
            orabooks_queue_email( $subscription->user_email, $subject, $message, 'payment_failed', 8 );
        }
        
        /**
         * Send second dunning email
         */
        private function send_second_dunning_email( $subscription ) {
            $subject = sprintf( 'Urgent: Payment Required - %s Subscription', $subscription->level_name );
            
            $message = $this->get_email_template( 'second_dunning', [
                'user_name' => $subscription->display_name,
                'level_name' => $subscription->level_name,
                'amount' => $subscription->price,
                'currency' => $subscription->currency_symbol,
                'update_payment_url' => $this->get_update_payment_url(),
                'retry_date' => date( 'Y-m-d H:i', strtotime( '+24 hours' ) ),
                'days_until_expiry' => 3
            ]);
            
            orabooks_queue_email( $subscription->user_email, $subject, $message, 'payment_failed', 9 );
        }
        
        /**
         * Send final dunning email
         */
        private function send_final_dunning_email( $subscription ) {
            $subject = sprintf( 'Final Notice: Subscription Expiration - %s', $subscription->level_name );
            
            $message = $this->get_email_template( 'final_dunning', [
                'user_name' => $subscription->display_name,
                'level_name' => $subscription->level_name,
                'amount' => $subscription->price,
                'currency' => $subscription->currency_symbol,
                'update_payment_url' => $this->get_update_payment_url(),
                'grace_period_end' => date( 'Y-m-d H:i', strtotime( '+7 days' ) )
            ]);
            
            orabooks_queue_email( $subscription->user_email, $subject, $message, 'payment_failed', 10 );
        }
        
        /**
         * Apply grace period
         */
        public function apply_grace_period( $subscription ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            $grace_period_end = date( 'Y-m-d H:i:s', strtotime( '+7 days' ) );
            
            $wpdb->update(
                $wpdb->orabooks_subscriptions,
                [
                    'status' => 'grace_period',
                    'ends_at' => $grace_period_end
                ],
                [ 'id' => $subscription->id ]
            );
            
            // Send grace period notification
            $this->send_grace_period_notification( $subscription );
            
            // Log grace period application
            orabooks_log_info( 'grace_period_applied', 'Grace period applied to subscription', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'grace_period_end' => $grace_period_end
            ]);
            
            // Trigger action for other integrations
            do_action( 'orabooks_grace_period_applied', $subscription->id, $subscription->user_id );
        }
        
        /**
         * Send grace period notification
         */
        private function send_grace_period_notification( $subscription ) {
            $subject = sprintf( 'Grace Period Applied - %s Subscription', $subscription->level_name );
            
            $message = $this->get_email_template( 'grace_period_applied', [
                'user_name' => $subscription->display_name,
                'level_name' => $subscription->level_name,
                'grace_period_end' => date( 'Y-m-d H:i', strtotime( '+7 days' ) ),
                'update_payment_url' => $this->get_update_payment_url(),
                'renew_url' => $this->get_renewal_url()
            ]);
            
            orabooks_queue_email( $subscription->user_email, $subject, $message, 'grace_period', 7 );
        }
        
        /**
         * Process grace period expirations
         */
        public function process_grace_period_expirations() {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Find subscriptions in grace period that have expired
            $expired_grace = $wpdb->get_results( "
                SELECT s.*, u.user_email, u.display_name, l.name as level_name
                FROM {$wpdb->orabooks_subscriptions} s
                JOIN {$wpdb->users} u ON s.user_id = u.ID
                JOIN {$wpdb->orabooks_levels} l ON s.level_id = l.id
                WHERE s.status = 'grace_period'
                AND s.ends_at < NOW()
            " );
            
            foreach ( $expired_grace as $subscription ) {
                $this->expire_subscription( $subscription );
            }
        }
        
        /**
         * Expire subscription
         */
        private function expire_subscription( $subscription ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Update status to expired
            $wpdb->update(
                $wpdb->orabooks_subscriptions,
                [ 'status' => 'expired' ],
                [ 'id' => $subscription->id ]
            );
            
            // Send expiration notification
            $this->send_expiration_notification( $subscription );
            
            // Log expiration
            orabooks_log_info( 'subscription_expired_grace', 'Subscription expired after grace period', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id
            ]);
            
            // Trigger action for other integrations
            do_action( 'orabooks_subscription_expired_grace', $subscription->id, $subscription->user_id );
        }
        
        /**
         * Send expiration notification
         */
        private function send_expiration_notification( $subscription ) {
            $subject = sprintf( 'Subscription Expired - %s', $subscription->level_name );
            
            $message = $this->get_email_template( 'subscription_expired_grace', [
                'user_name' => $subscription->display_name,
                'level_name' => $subscription->level_name,
                'renew_url' => $this->get_renewal_url(),
                'support_url' => $this->get_support_url()
            ]);
            
            orabooks_queue_email( $subscription->user_email, $subject, $message, 'subscription_expired', 6 );
        }
        
        /**
         * Get email templates
         */
        private function get_email_template( $template, $variables = [] ) {
            $site_name = get_bloginfo( 'name' );
            $site_url = home_url();
            
            $templates = [
                'first_dunning' => "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <div style='background: #ffc107; color: #333; padding: 20px; text-align: center;'>
                            <h1 style='margin: 0; font-size: 24px;'>Payment Issue Detected</h1>
                        </div>
                        <div style='padding: 30px; background: #f9f9f9;'>
                            <h2 style='color: #333; margin-top: 0;'>Hi {user_name},</h2>
                            <p style='color: #666; line-height: 1.6;'>We encountered an issue processing your payment for the {level_name} subscription.</p>
                            <div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                                <p style='margin: 0; color: #856404;'><strong>Amount:</strong> {currency}{amount}</p>
                                <p style='margin: 10px 0 0 0; color: #856404;'><strong>Next Retry:</strong> {retry_date}</p>
                            </div>
                            <p style='color: #666; line-height: 1.6;'>Please update your payment method to ensure uninterrupted service.</p>
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='{update_payment_url}' style='background: #43a62d; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Update Payment Method</a>
                            </div>
                        </div>
                        <div style='background: #333; color: white; padding: 20px; text-align: center; font-size: 12px;'>
                            <p style='margin: 0;'>&copy; " . date('Y') . " {$site_name}. All rights reserved.</p>
                        </div>
                    </div>
                ",
                
                'second_dunning' => "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <div style='background: #fd7e14; color: white; padding: 20px; text-align: center;'>
                            <h1 style='margin: 0; font-size: 24px;'>Urgent: Payment Required</h1>
                        </div>
                        <div style='padding: 30px; background: #f9f9f9;'>
                            <h2 style='color: #333; margin-top: 0;'>Hi {user_name},</h2>
                            <p style='color: #666; line-height: 1.6;'>This is your second notice regarding the failed payment for your {level_name} subscription.</p>
                            <div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                                <p style='margin: 0; color: #721c24;'><strong>Amount:</strong> {currency}{amount}</p>
                                <p style='margin: 10px 0 0 0; color: #721c24;'><strong>Days until expiry:</strong> {days_until_expiry}</p>
                                <p style='margin: 10px 0 0 0; color: #721c24;'><strong>Final retry:</strong> {retry_date}</p>
                            </div>
                            <p style='color: #666; line-height: 1.6;'>If payment fails again, your subscription will enter a grace period.</p>
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='{update_payment_url}' style='background: #dc3545; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Update Payment Now</a>
                            </div>
                        </div>
                        <div style='background: #333; color: white; padding: 20px; text-align: center; font-size: 12px;'>
                            <p style='margin: 0;'>&copy; " . date('Y') . " {$site_name}. All rights reserved.</p>
                        </div>
                    </div>
                ",
                
                'final_dunning' => "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <div style='background: #dc3545; color: white; padding: 20px; text-align: center;'>
                            <h1 style='margin: 0; font-size: 24px;'>Final Notice</h1>
                        </div>
                        <div style='padding: 30px; background: #f9f9f9;'>
                            <h2 style='color: #333; margin-top: 0;'>Hi {user_name},</h2>
                            <p style='color: #666; line-height: 1.6;'>This is your final notice regarding the failed payment for your {level_name} subscription.</p>
                            <div style='background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                                <p style='margin: 0; color: #0c5460;'><strong>Amount:</strong> {currency}{amount}</p>
                                <p style='margin: 10px 0 0 0; color: #0c5460;'><strong>Grace period ends:</strong> {grace_period_end}</p>
                            </div>
                            <p style='color: #666; line-height: 1.6;'>We will apply a 7-day grace period to your subscription. After that, it will be permanently expired.</p>
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='{update_payment_url}' style='background: #dc3545; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Pay Now to Avoid Expiration</a>
                            </div>
                        </div>
                        <div style='background: #333; color: white; padding: 20px; text-align: center; font-size: 12px;'>
                            <p style='margin: 0;'>&copy; " . date('Y') . " {$site_name}. All rights reserved.</p>
                        </div>
                    </div>
                ",
                
                'grace_period_applied' => "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <div style='background: #6f42c1; color: white; padding: 20px; text-align: center;'>
                            <h1 style='margin: 0; font-size: 24px;'>Grace Period Applied</h1>
                        </div>
                        <div style='padding: 30px; background: #f9f9f9;'>
                            <h2 style='color: #333; margin-top: 0;'>Hi {user_name},</h2>
                            <p style='color: #666; line-height: 1.6;'>Due to payment issues, we've applied a 7-day grace period to your {level_name} subscription.</p>
                            <div style='background: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                                <p style='margin: 0; color: #004085;'><strong>Grace period ends:</strong> {grace_period_end}</p>
                            </div>
                            <p style='color: #666; line-height: 1.6;'>You can continue using all features during this period. Please update your payment method to avoid service interruption.</p>
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='{update_payment_url}' style='background: #6f42c1; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Update Payment Method</a>
                            </div>
                        </div>
                        <div style='background: #333; color: white; padding: 20px; text-align: center; font-size: 12px;'>
                            <p style='margin: 0;'>&copy; " . date('Y') . " {$site_name}. All rights reserved.</p>
                        </div>
                    </div>
                ",
                
                'subscription_expired_grace' => "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <div style='background: #6c757d; color: white; padding: 20px; text-align: center;'>
                            <h1 style='margin: 0; font-size: 24px;'>Subscription Expired</h1>
                        </div>
                        <div style='padding: 30px; background: #f9f9f9;'>
                            <h2 style='color: #333; margin-top: 0;'>Hi {user_name},</h2>
                            <p style='color: #666; line-height: 1.6;'>Your {level_name} subscription has expired after the grace period.</p>
                            <p style='color: #666; line-height: 1.6;'>We hope you enjoyed using our services! You can renew your subscription at any time to continue accessing all features.</p>
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='{renew_url}' style='background: #43a62d; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Renew Subscription</a>
                            </div>
                            <p style='color: #666; line-height: 1.6; text-align: center; margin-top: 20px;'>If you have any questions, please contact our <a href='{support_url}'>support team</a>.</p>
                        </div>
                        <div style='background: #333; color: white; padding: 20px; text-align: center; font-size: 12px;'>
                            <p style='margin: 0;'>&copy; " . date('Y') . " {$site_name}. All rights reserved.</p>
                        </div>
                    </div>
                "
            ];
            
            $template_content = $templates[$template] ?? '';
            
            // Replace variables
            foreach ( $variables as $key => $value ) {
                $template_content = str_replace( '{' . $key . '}', (string) $value, (string) $template_content );
            }
            
            return $template_content;
        }
        
        /**
         * Get update payment URL
         */
        private function get_update_payment_url() {
            return home_url( '/client-dashboard/?tab=billing' );
        }
        
        /**
         * Get renewal URL
         */
        private function get_renewal_url() {
            return home_url( '/pricing/' );
        }
        
        /**
         * Get support URL
         */
        private function get_support_url() {
            return home_url( '/contact/' );
        }
        
        /**
         * Get dunning statistics
         */
        public static function get_dunning_stats() {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            $stats = [];
            
            // Subscriptions in dunning process
            $stats['in_dunning'] = $wpdb->get_var( "
                SELECT COUNT(*) FROM {$wpdb->orabooks_subscriptions}
                WHERE status IN ('active', 'grace_period')
                AND renewal_attempts > 0
                AND renewal_attempts < 3
            " ) ?: 0;
            
            // Subscriptions in grace period
            $stats['in_grace_period'] = $wpdb->get_var( "
                SELECT COUNT(*) FROM {$wpdb->orabooks_subscriptions}
                WHERE status = 'grace_period'
            " ) ?: 0;
            
            // Expired after grace period (last 30 days)
            $stats['expired_grace'] = $wpdb->get_var( "
                SELECT COUNT(*) FROM {$wpdb->orabooks_subscriptions}
                WHERE status = 'expired'
                AND ends_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            " ) ?: 0;
            
            // Recovery rate
            $total_dunning = $wpdb->get_var( "
                SELECT COUNT(*) FROM {$wpdb->orabooks_subscriptions}
                WHERE renewal_attempts > 0
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            " ) ?: 1;
            
            $recovered = $wpdb->get_var( "
                SELECT COUNT(*) FROM {$wpdb->orabooks_subscriptions}
                WHERE status = 'active'
                AND renewal_attempts > 0
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            " ) ?: 0;
            
            $stats['recovery_rate'] = round( ( $recovered / $total_dunning ) * 100, 1 );
            
            return $stats;
        }
    }
}

// Initialize the dunning manager
OraBooks_Dunning_Manager::get_instance();

// Schedule cron jobs on plugin initialization
add_action( 'init', function() {
    if ( ! wp_next_scheduled( 'orabooks_grace_period_cron' ) ) {
        wp_schedule_event( time(), 'hourly', 'orabooks_grace_period_cron' );
    }
});

add_action( 'orabooks_grace_period_cron', function() {
    $dunning_manager = OraBooks_Dunning_Manager::get_instance();
    $dunning_manager->process_grace_period_expirations();
});

// Clear cron jobs on deactivation
add_action( 'orabooks_deactivate', function() {
    wp_clear_scheduled_hook( 'orabooks_grace_period_cron' );
    wp_clear_scheduled_hook( 'orabooks_daily_cron' );
    wp_clear_scheduled_hook( 'orabooks_hourly_cron' );
    wp_clear_scheduled_hook( 'orabooks_email_cron' );
    wp_clear_scheduled_hook( 'orabooks_weekly_cron' );
    wp_clear_scheduled_hook( 'orabooks_audit_cleanup' );
});
