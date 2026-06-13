<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'OraBooks_Subscription_Manager' ) ) {
    class OraBooks_Subscription_Manager {
        
        private static $instance = null;
        
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        public function __construct() {
            add_action( 'orabooks_daily_cron', [ $this, 'process_renewals' ] );
            add_action( 'orabooks_hourly_cron', [ $this, 'send_renewal_reminders' ] );
            add_action( 'orabooks_hourly_cron', [ $this, 'check_expired_subscriptions' ] );
        }
        
        /**
         * Process subscription renewals
         * Runs daily to process subscriptions expiring in the next 3 days
         */
        public function process_renewals() {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Build Guide Compliance: Get current mode
            $current_mode = class_exists('OraBooks_Mode_Manager') ? OraBooks_Mode_Manager::get_current_mode() : 'business';
            
            // Find subscriptions expiring in the next 3 days that have auto-renew enabled
            $expiring_subscriptions = $wpdb->get_results( $wpdb->prepare( "
                SELECT * FROM {$wpdb->orabooks_subscriptions}
                WHERE ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
                AND status = 'active'
                AND auto_renew = %d
                AND (last_renewal_attempt IS NULL 
                     OR last_renewal_attempt < DATE_SUB(NOW(), INTERVAL 24 HOUR)
                     OR renewal_attempts < 3)
            ", 1 ) );
            
            foreach ( $expiring_subscriptions as $subscription ) {
                $this->attempt_renewal( $subscription, $current_mode );
            }
        }
        
        /**
         * Attempt to renew a subscription
         */
        private function attempt_renewal( $subscription, $mode = 'business' ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Update renewal attempt tracking with audit fields
            $wpdb->update(
                $wpdb->orabooks_subscriptions,
                [
                    'renewal_attempts' => $subscription->renewal_attempts + 1,
                    'last_renewal_attempt' => current_time( 'mysql' ),
                    'updated_by' => 0 // System update
                ],
                [ 'id' => $subscription->id ]
            );
            
            // Get level information
            $level = orabooks_get_level( $subscription->level_id );
            if ( ! $level ) {
                orabooks_log_error( 'renewal_failed', 'Level not found for subscription', [
                    'subscription_id' => $subscription->id,
                    'level_id' => $subscription->level_id
                ]);
                return false;
            }
            
            // Get user information
            $user = get_user_by( 'id', $subscription->user_id );
            if ( ! $user ) {
                orabooks_log_error( 'renewal_failed', 'User not found for subscription', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id
                ]);
                return false;
            }
            
            // Process payment based on gateway
            $payment_result = $this->process_renewal_payment( $subscription, $level, $user );
            
            if ( $payment_result['success'] ) {
                $this->handle_successful_renewal( $subscription, $payment_result, $mode );
            } else {
                $this->handle_failed_renewal( $subscription, $payment_result, $mode );
            }
            
            // Build Guide Compliance: Log renewal attempt for audit trail
            if (class_exists('OraBooks_Audit_Logger')) {
                $logger = OraBooks_Audit_Logger::get_instance();
                $logger->log_action(array(
                    'user_id' => $subscription->user_id,
                    'action_type' => 'subscription_renewal_attempt',
                    'action_description' => sprintf('Subscription renewal attempt: %s', $payment_result['success'] ? 'success' : 'failed'),
                    'mode' => $mode,
                    'entity_type' => 'subscription',
                    'entity_id' => $subscription->id,
                    'after_state' => array(
                        'renewal_attempts' => $subscription->renewal_attempts + 1,
                        'success' => $payment_result['success']
                    )
                ));
            }
            
            return $payment_result['success'];
        }
        
        /**
         * Process renewal payment
         */
        private function process_renewal_payment( $subscription, $level, $user ) {
            $gateway = $subscription->gateway;
            
            // Get stored payment method from subscription meta
            $payment_method = $this->get_stored_payment_method( $subscription );
            
            if ( ! $payment_method ) {
                return [
                    'success' => false,
                    'error' => 'No payment method available',
                    'error_code' => 'no_payment_method'
                ];
            }
            
            // Process payment based on gateway
            switch ( $gateway ) {
                case 'stripe':
                    return $this->process_stripe_renewal( $subscription, $level, $payment_method );
                    
                case 'sslcommerz':
                    return $this->process_sslcommerz_renewal( $subscription, $level, $payment_method );
                    
                case 'shurjopay':
                    return $this->process_shurjopay_renewal( $subscription, $level, $payment_method );
                    
                default:
                    return [
                        'success' => false,
                        'error' => 'Unsupported gateway for auto-renewal',
                        'error_code' => 'unsupported_gateway'
                    ];
            }
        }
        
        /**
         * Process Stripe renewal
         */
        private function process_stripe_renewal( $subscription, $level, $payment_method ) {
            if ( ! class_exists( 'Stripe\Stripe' ) ) {
                return [
                    'success' => false,
                    'error' => 'Stripe library not available',
                    'error_code' => 'stripe_not_available'
                ];
            }
            
            try {
                $stripe_secret_key = get_option( 'orabooks_stripe_secret_key' );
                if ( ! $stripe_secret_key ) {
                    throw new Exception( 'Stripe secret key not configured' );
                }
                
                \Stripe\Stripe::setApiKey( $stripe_secret_key );
                
                // Create payment intent
                $payment_intent = \Stripe\PaymentIntent::create([
                    'amount' => $level->price * 100, // Convert to cents
                    'currency' => strtolower( $level->currency ),
                    'customer' => $payment_method['customer_id'],
                    'payment_method' => $payment_method['payment_method_id'],
                    'confirmation_method' => 'automatic',
                    'confirm' => true,
                    'metadata' => [
                        'subscription_id' => $subscription->id,
                        'user_id' => $subscription->user_id,
                        'level_id' => $level->id
                    ]
                ]);
                
                if ( $payment_intent->status === 'succeeded' ) {
                    return [
                        'success' => true,
                        'transaction_id' => $payment_intent->id,
                        'amount' => $level->price
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'Payment failed: ' . $payment_intent->last_payment_error->message ?? 'Unknown error',
                        'error_code' => 'payment_failed'
                    ];
                }
                
            } catch ( \Exception $e ) {
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'error_code' => 'stripe_error'
                ];
            }
        }
        
        /**
         * Process SSLCommerz renewal
         */
        private function process_sslcommerz_renewal( $subscription, $level, $payment_method ) {
            // SSLCommerz doesn't support true auto-renewal, so we'll create a manual renewal process
            // This would typically involve sending the user a payment link
            
            return [
                'success' => false,
                'error' => 'SSLCommerz requires manual renewal',
                'error_code' => 'manual_renewal_required'
            ];
        }
        
        /**
         * Process ShurjoPay renewal
         */
        private function process_shurjopay_renewal( $subscription, $level, $payment_method ) {
            // Similar to SSLCommerz, ShurjoPay requires manual renewal
            
            return [
                'success' => false,
                'error' => 'ShurjoPay requires manual renewal',
                'error_code' => 'manual_renewal_required'
            ];
        }
        
        /**
         * Handle successful renewal
         */
        private function handle_successful_renewal( $subscription, $payment_result, $mode = 'business' ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Get level information
            $level = orabooks_get_level( $subscription->level_id );
            if (!$level) {
                return false;
            }
            
            // Calculate new end date
            $new_end_date = $this->calculate_new_end_date( $subscription->ends_at, $level->billing_period );
            
            // Update subscription
            $wpdb->update(
                $wpdb->orabooks_subscriptions,
                [
                    'ends_at' => $new_end_date,
                    'status' => 'active',
                    'renewal_attempts' => 0,
                    'last_renewal_attempt' => null
                ],
                [ 'id' => $subscription->id ]
            );
            
            // Create renewal order
            $order_id = $this->create_renewal_order( $subscription, $payment_result );
            
            // Send renewal confirmation email
            $this->send_renewal_confirmation_email( $subscription, $order_id );
            
            // Log successful renewal
            orabooks_log_info( 'renewal_success', 'Subscription renewed successfully', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'order_id' => $order_id,
                'amount' => $payment_result['amount'],
                'transaction_id' => $payment_result['transaction_id']
            ]);
            
            // Trigger action for other integrations
            do_action( 'orabooks_subscription_renewed', $subscription->id, $subscription->user_id, $order_id );
        }
        
        /**
         * Handle failed renewal
         */
        private function handle_failed_renewal( $subscription, $payment_result, $mode = 'business' ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Check if this is the 3rd attempt
            if ( $subscription->renewal_attempts >= 3 ) {
                // Apply grace period
                $this->apply_grace_period( $subscription );
            } else {
                // Send payment failed email
                $this->send_payment_failed_email( $subscription, $payment_result );
            }
            
            // Log failed renewal
            orabooks_log_warning( 'renewal_failed', 'Subscription renewal failed', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'attempt' => $subscription->renewal_attempts,
                'error' => $payment_result['error'],
                'error_code' => $payment_result['error_code']
            ]);
        }
        
        /**
         * Calculate new end date based on billing period
         */
        private function calculate_new_end_date( $current_end_date, $billing_period ) {
            switch ( $billing_period ) {
                case 'monthly':
                    return date( 'Y-m-d H:i:s', strtotime( $current_end_date . ' +1 month' ) );
                    
                case 'yearly':
                    return date( 'Y-m-d H:i:s', strtotime( $current_end_date . ' +1 year' ) );
                    
                case 'quarterly':
                    return date( 'Y-m-d H:i:s', strtotime( $current_end_date . ' +3 months' ) );
                    
                case 'weekly':
                    return date( 'Y-m-d H:i:s', strtotime( $current_end_date . ' +1 week' ) );
                    
                default:
                    return date( 'Y-m-d H:i:s', strtotime( $current_end_date . ' +1 month' ) );
            }
        }
        
        /**
         * Create renewal order
         */
        private function create_renewal_order( $subscription, $payment_result ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            $order_data = [
                'order_id' => 'REN-' . time() . '-' . $subscription->user_id,
                'user_id' => $subscription->user_id,
                'level_id' => $subscription->level_id,
                'gateway' => $subscription->gateway,
                'amount' => $payment_result['amount'],
                'status' => 'completed',
                'created_at' => current_time( 'mysql' ),
                'meta' => wp_json_encode( [
                    'type' => 'renewal',
                    'subscription_id' => $subscription->id,
                    'transaction_id' => $payment_result['transaction_id']
                ] )
            ];
            
            $wpdb->insert( $wpdb->orabooks_orders, $order_data );
            
            return $wpdb->insert_id;
        }
        
        /**
         * Send renewal confirmation email
         */
        private function send_renewal_confirmation_email( $subscription, $order_id ) {
            $user = get_user_by( 'id', $subscription->user_id );
            $level = orabooks_get_level( $subscription->level_id );
            
            if ( ! $user || ! $level ) {
                return false;
            }
            
            $subject = sprintf( 'Your %s subscription has been renewed', $level->name );
            
            $message = $this->get_email_template( 'renewal_confirmation', [
                'user_name' => $user->display_name,
                'level_name' => $level->name,
                'amount' => $level->price,
                'currency' => $level->currency_symbol,
                'next_billing_date' => $subscription->ends_at,
                'manage_url' => $this->get_manage_subscription_url()
            ]);
            
            return wp_mail( $user->user_email, $subject, $message );
        }
        
        /**
         * Send payment failed email
         */
        private function send_payment_failed_email( $subscription, $payment_result ) {
            $user = get_user_by( 'id', $subscription->user_id );
            $level = orabooks_get_level( $subscription->level_id );
            
            if ( ! $user || ! $level ) {
                return false;
            }
            
            $attempt = $subscription->renewal_attempts;
            $subject = sprintf( 'Payment Failed - Attempt %d of 3', $attempt );
            
            $message = $this->get_email_template( 'payment_failed', [
                'user_name' => $user->display_name,
                'level_name' => $level->name,
                'amount' => $level->price,
                'currency' => $level->currency_symbol,
                'attempt' => $attempt,
                'error_message' => $payment_result['error'],
                'update_payment_url' => $this->get_update_payment_url(),
                'retry_date' => date( 'Y-m-d', strtotime( '+24 hours' ) )
            ]);
            
            return wp_mail( $user->user_email, $subject, $message );
        }
        
        /**
         * Send renewal reminders
         */
        public function send_renewal_reminders() {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Send reminders for subscriptions expiring in 7 days
            $expiring_soon = $wpdb->get_results( $wpdb->prepare( "
                SELECT * FROM {$wpdb->orabooks_subscriptions}
                WHERE ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                AND status = 'active'
                AND auto_renew = %d
            ", 0 ) );
            
            foreach ( $expiring_soon as $subscription ) {
                $this->send_renewal_reminder_email( $subscription );
            }
        }
        
        /**
         * Send renewal reminder email
         */
        private function send_renewal_reminder_email( $subscription ) {
            $user = get_user_by( 'id', $subscription->user_id );
            $level = orabooks_get_level( $subscription->level_id );
            
            if ( ! $user || ! $level ) {
                return false;
            }
            
            $days_until_expiry = floor( ( strtotime( $subscription->ends_at ) - time() ) / ( 24 * 60 * 60 ) );
            
            $subject = sprintf( 'Your %s subscription expires in %d days', $level->name, $days_until_expiry );
            
            $message = $this->get_email_template( 'renewal_reminder', [
                'user_name' => $user->display_name,
                'level_name' => $level->name,
                'expiry_date' => $subscription->ends_at,
                'days_until_expiry' => $days_until_expiry,
                'renew_url' => $this->get_renewal_url()
            ]);
            
            return wp_mail( $user->user_email, $subject, $message );
        }
        
        /**
         * Check for expired subscriptions
         */
        public function check_expired_subscriptions() {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Find subscriptions that have expired
            $expired = $wpdb->get_results( $wpdb->prepare( "
                SELECT * FROM {$wpdb->orabooks_subscriptions}
                WHERE ends_at < NOW()
                AND status IN (%s, %s)
            ", 'active', 'grace_period' ) );
            
            foreach ( $expired as $subscription ) {
                $this->handle_expired_subscription( $subscription );
            }
        }
        
        /**
         * Handle expired subscription
         */
        private function handle_expired_subscription( $subscription ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Update status to expired
            $wpdb->update(
                $wpdb->orabooks_subscriptions,
                [ 'status' => 'expired' ],
                [ 'id' => $subscription->id ]
            );
            
            // Send expiration email
            $this->send_expiration_email( $subscription );
            
            // Log expiration
            orabooks_log_info( 'subscription_expired', 'Subscription expired', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id
            ]);
            
            // Trigger action
            do_action( 'orabooks_subscription_expired', $subscription->id, $subscription->user_id );
        }
        
        /**
         * Send expiration email
         */
        private function send_expiration_email( $subscription ) {
            $user = get_user_by( 'id', $subscription->user_id );
            $level = orabooks_get_level( $subscription->level_id );
            
            if ( ! $user || ! $level ) {
                return false;
            }
            
            $subject = sprintf( 'Your %s subscription has expired', $level->name );
            
            $message = $this->get_email_template( 'subscription_expired', [
                'user_name' => $user->display_name,
                'level_name' => $level->name,
                'expiry_date' => $subscription->ends_at,
                'renew_url' => $this->get_renewal_url()
            ]);
            
            return wp_mail( $user->user_email, $subject, $message );
        }
        
        /**
         * Apply grace period
         */
        private function apply_grace_period( $subscription ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Extend subscription by 7 days and set status to grace_period
            $grace_period_end = date( 'Y-m-d H:i:s', strtotime( $subscription->ends_at . ' +7 days' ) );
            
            $wpdb->update(
                $wpdb->orabooks_subscriptions,
                [
                    'ends_at' => $grace_period_end,
                    'status' => 'grace_period',
                    'renewal_attempts' => 0,
                    'last_renewal_attempt' => null
                ],
                [ 'id' => $subscription->id ]
            );
            
            // Send grace period notification
            $this->send_grace_period_email( $subscription );
            
            orabooks_log_info( 'grace_period_applied', 'Grace period applied to subscription', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'grace_period_end' => $grace_period_end
            ]);
        }
        
        /**
         * Send grace period email
         */
        private function send_grace_period_email( $subscription ) {
            $user = get_user_by( 'id', $subscription->user_id );
            $level = orabooks_get_level( $subscription->level_id );
            
            if ( ! $user || ! $level ) {
                return false;
            }
            
            $subject = sprintf( 'Payment Issues - %s Subscription Grace Period', $level->name );
            
            $message = $this->get_email_template( 'grace_period', [
                'user_name' => $user->display_name,
                'level_name' => $level->name,
                'grace_period_end' => date( 'Y-m-d H:i:s', strtotime( $subscription->ends_at . ' +7 days' ) ),
                'update_payment_url' => $this->get_update_payment_url()
            ]);
            
            return wp_mail( $user->user_email, $subject, $message );
        }
        
        /**
         * Get stored payment method
         */
        private function get_stored_payment_method( $subscription ) {
            $meta = json_decode( $subscription->meta ?: '{}', true );
            return $meta['payment_method'] ?? null;
        }
        
        /**
         * Get email template
         */
        private function get_email_template( $template, $variables = [] ) {
            $templates = [
                'renewal_confirmation' => "
                    Hi {user_name},
                    
                    Your {level_name} subscription has been successfully renewed!
                    
                    Amount: {currency}{amount}
                    Next billing date: {next_billing_date}
                    
                    You can manage your subscription here: {manage_url}
                    
                    Thank you for your continued support!
                ",
                'payment_failed' => "
                    Hi {user_name},
                    
                    We were unable to process your {level_name} subscription renewal payment.
                    
                    Amount: {currency}{amount}
                    Attempt: {attempt} of 3
                    Error: {error_message}
                    
                    Please update your payment method here: {update_payment_url}
                    
                    We will retry the payment on: {retry_date}
                    
                    Thank you,
                    The Team
                ",
                'renewal_reminder' => "
                    Hi {user_name},
                    
                    Your {level_name} subscription will expire in {days_until_expiry} days.
                    
                    Expiry date: {expiry_date}
                    
                    Renew your subscription here: {renew_url}
                    
                    Thank you,
                    The Team
                ",
                'subscription_expired' => "
                    Hi {user_name},
                    
                    Your {level_name} subscription has expired on {expiry_date}.
                    
                    To continue using our services, please renew your subscription: {renew_url}
                    
                    Thank you,
                    The Team
                ",
                'grace_period' => "
                    Hi {user_name},
                    
                    We encountered issues processing your {level_name} subscription payment.
                    
                    We've applied a 7-day grace period until {grace_period_end}.
                    
                    Please update your payment method here: {update_payment_url}
                    
                    Thank you,
                    The Team
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
         * Get manage subscription URL
         */
        private function get_manage_subscription_url() {
            return home_url( '/client-dashboard/?tab=subscription' );
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
    }
}

// Initialize the subscription manager
OraBooks_Subscription_Manager::get_instance();

// Helper functions
function orabooks_process_subscription_renewal( $subscription_id ) {
    $manager = OraBooks_Subscription_Manager::get_instance();
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    $subscription = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->orabooks_subscriptions} WHERE id = %d",
        $subscription_id
    ));
    
    if ( $subscription ) {
        return $manager->attempt_renewal( $subscription );
    }
    
    return false;
}
