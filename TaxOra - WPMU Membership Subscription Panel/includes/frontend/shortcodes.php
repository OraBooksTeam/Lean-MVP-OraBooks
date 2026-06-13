<?php
// Handle free plan activation
add_action('init', 'orabooks_handle_free_plan_activation');
function orabooks_handle_free_plan_activation() {
    if (isset($_GET['action']) && $_GET['action'] === 'orabooks_activate_free_plan' && isset($_GET['level_id'])) {
        try {
            $level_id = intval($_GET['level_id']);
            $nonce = $_GET['nonce'] ?? '';
            
            error_log('Free plan activation started for level: ' . $level_id);
            
            if (!wp_verify_nonce($nonce, 'orabooks_activate_free_plan_' . $level_id)) {
                error_log('Free plan activation: Security check failed');
                wp_die('Security check failed.');
            }
            
            if (!is_user_logged_in()) {
                error_log('Free plan activation: User not logged in');
                wp_die('You must be logged in to activate a plan.');
            }
            
            global $wpdb;
            
            // Handle multisite tables with error handling
            if (function_exists('orabooks_handle_multisite_tables')) {
                orabooks_handle_multisite_tables();
            }
            
            // Check if table property exists
            if (!isset($wpdb->orabooks_levels) || !isset($wpdb->orabooks_subscriptions)) {
                error_log('Free plan activation: Database tables not defined');
                wp_die('Database tables not configured. Please contact administrator.');
            }
            
            error_log('Free plan activation: Tables: ' . $wpdb->orabooks_levels . ', ' . $wpdb->orabooks_subscriptions);
            
            // Verify the level exists and is free
            $level = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->orabooks_levels} WHERE id = %d AND is_active = 1 AND price = 0", $level_id));
            if (!$level) {
                error_log('Free plan activation: Level not found - ID: ' . $level_id);
                wp_die('Free plan not found or not available.');
            }
            
            $user_id = get_current_user_id();
            $site_id = get_current_blog_id();
            
            error_log('Free plan activation: User ID: ' . $user_id . ', Site ID: ' . $site_id);
            
            // Check if user already has this plan
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->orabooks_subscriptions} 
                 WHERE user_id = %d AND level_id = %d AND status = 'active'",
                $user_id, $level_id
            ));
            
            if ($existing) {
                error_log('Free plan activation: User already has this plan');
                wp_die('You already have this plan activated.');
            }
            
            // Create the subscription
            $subscription_id = 'free_' . $user_id . '_' . time();
            $result = $wpdb->insert(
                $wpdb->orabooks_subscriptions,
                array(
                    'subscription_id' => $subscription_id,
                    'user_id' => $user_id,
                    'level_id' => $level_id,
                    'gateway' => 'free',
                    'status' => 'active',
                    'started_at' => current_time('mysql'),
                    'ends_at' => null, // Free plans don't expire
                    'meta' => json_encode(array(
                        'activation_method' => 'free_plan_activation',
                        'activated_at' => current_time('mysql')
                    ))
                ),
                array('%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s')
            );
            
            if ($result === false) {
                error_log('Free plan activation: Database insert failed - ' . $wpdb->last_error);
                wp_die('Failed to activate plan. Please try again.');
            }
            
            // Also update user meta for compatibility with features shortcode
            update_user_meta($user_id, 'orabooks_level', $level_id);
            
            error_log('Free plan activation: Success, redirecting...');
            
            // Redirect to success page or dashboard
            $dashboard_url = home_url('/dashboard'); // Use home_url as fallback
            if (function_exists('orabooks_get_feature_access_url')) {
                $dashboard_url = orabooks_get_feature_access_url();
            }
            
            wp_redirect(add_query_arg('free_plan_activated', '1', $dashboard_url));
            exit;
            
        } catch (Exception $e) {
            error_log('Free plan activation exception: ' . $e->getMessage());
            wp_die('An error occurred while activating the plan: ' . $e->getMessage());
        }
    }
}

// Register all shortcodes
add_shortcode( 'orabooks_levels', 'orabooks_levels_shortcode' );
add_shortcode( 'orabooks_checkout', 'orabooks_checkout_shortcode' );
add_shortcode( 'orabooks_my_account', 'orabooks_account_shortcode' );
add_shortcode( 'orabooks_confirmation', 'orabooks_confirmation_shortcode' );
add_shortcode( 'orabooks_features', 'orabooks_features_shortcode' );
add_shortcode( 'orabooks_login_logout', 'orabooks_login_logout_shortcode' );
add_shortcode( 'orabooks_payment_success', 'orabooks_payment_success_shortcode' );
add_shortcode( 'orabooks_payment_failed', 'orabooks_payment_failed_shortcode' );
add_shortcode( 'orabooks_register', 'orabooks_register_shortcode' );
add_shortcode( 'orabooks_upgrade_plan', 'orabooks_upgrade_plan_shortcode' );

// Enhanced page detection with auto-creation
function orabooks_get_or_create_page($title, $content = '') {
    // If site admin has disabled automatic page creation, only attempt to find existing pages
    if ( get_option( 'orabooks_prevent_auto_pages', false ) ) {
        if (function_exists('orabooks_get_page_by_title')) {
            return orabooks_get_page_by_title($title);
        } else {
            $pages = get_posts(array(
                'post_type' => 'page',
                'title' => $title,
                'post_status' => 'publish',
                'posts_per_page' => 1
            ));
            return !empty($pages) ? $pages[0] : null;
        }
    }
    // Use the function from main plugin file
    if (function_exists('orabooks_get_page_by_title')) {
        $page = orabooks_get_page_by_title($title);
    } else {
        // Fallback if function doesn't exist
        $pages = get_posts(array(
            'post_type' => 'page',
            'title' => $title,
            'post_status' => 'publish',
            'posts_per_page' => 1
        ));
        $page = !empty($pages) ? $pages[0] : null;
    }
    
    if (!$page) {
        // Create new page
        $page_id = wp_insert_post(array(
            'post_title' => $title,
            'post_name' => sanitize_title($title),
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id()
        ));
        
        if (!is_wp_error($page_id)) {
            $page = get_post($page_id);
        }
    }
    
    return $page;
}

// Levels/Pricing shortcode
function orabooks_levels_shortcode() {
    global $wpdb;
    
    // Show success message if free plan was just activated
    if (isset($_GET['free_plan_activated']) && $_GET['free_plan_activated'] === '1') {
        echo '<div class="orabooks-success-message" style="background: #28a745; color: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #28a745; text-align: center; font-size: 16px; box-shadow: 0 2px 10px rgba(40, 167, 69, 0.2);">';
        echo '<div style="font-size: 24px; margin-bottom: 10px;">🎉</div>';
        echo '<strong style="font-size: 18px;">Plan Successfully Activated!</strong><br>';
        echo '<span style="margin-top: 5px; display: block;">Your free plan has been activated. You can now access all the features included in your plan.</span>';
        echo '</div>';
    }
    
    $current_group_id = isset( $_GET['orabooks_group'] ) ? intval( $_GET['orabooks_group'] ) : 0;
    
    // Use hardcoded BDT currency
    $symbol = '৳';
    $pos = 'after';

    ob_start();
    
    echo '<div class="orabooks-levels-container">';
    
    if ( $current_group_id ) {
        orabooks_display_group_levels( $current_group_id, $symbol, $pos );
    } else {
        orabooks_display_groups_grid( $symbol, $pos );
    }
    
    echo '</div>';
    return ob_get_clean();
}

function orabooks_display_groups_grid( $symbol = '৳', $pos = 'after' ) {
    global $wpdb;
    
    $groups = $wpdb->get_results("
        SELECT g.*, 
               (SELECT COUNT(*) FROM {$wpdb->orabooks_levels} l WHERE l.group_id = g.id AND l.is_active = 1) as levels_count,
               (SELECT MIN(price) FROM {$wpdb->orabooks_levels} l WHERE l.group_id = g.id AND l.price > 0 AND l.is_active = 1) as min_price,
               (SELECT MAX(price) FROM {$wpdb->orabooks_levels} l WHERE l.group_id = g.id AND l.is_active = 1) as max_price
        FROM {$wpdb->orabooks_groups} g 
        ORDER BY g.name
    ");

    if ( ! empty( $groups ) ) {
        echo '<div class="orabooks-groups-section">';
        echo '<h2 class="orabooks-section-title">Choose Your Membership Plan</h2>';
        echo '<p class="orabooks-section-subtitle">Select a category to view available plans</p>';
        echo '<div class="orabooks-groups-grid">';
        
        foreach ( $groups as $g ) {
            $has_levels = $g->levels_count > 0;
            echo '<div class="orabooks-group-card ' . ( ! $has_levels ? 'no-levels' : '' ) . '">';
            echo '<div class="group-header">';
            echo '<h3>' . esc_html( $g->name ) . '</h3>';
            echo '</div>';
            
            if ( $has_levels ) {
                if ( $g->min_price !== null ) {
                    $min_price = number_format( $g->min_price, 2 );
                    $max_price = number_format( $g->max_price, 2 );
                    $min_display = $min_price . '৳';
                    $max_display = $max_price . '৳';
                    
                    echo '<div class="group-price-range">';
                    if ( $g->min_price == $g->max_price ) {
                        echo '<span class="price-amount">' . $min_display . '</span>';
                    } else {
                        echo '<span class="price-amount">' . $min_display . ' - ' . $max_display . '</span>';
                    }
                    echo '</div>';
                }
                
                echo '<div class="group-levels-count">' . intval( $g->levels_count ) . ' plan(s) available</div>';
                
                if ( ! empty( $g->description ) ) {
                    echo '<div class="group-description">' . esc_html( $g->description ) . '</div>';
                }
                
                $group_url = add_query_arg( array( 'orabooks_group' => $g->id ), get_permalink() );
                echo '<a href="' . esc_url( $group_url ) . '" class="orabooks-btn orabooks-btn-primary">View Plans</a>';
            } else {
                echo '<div class="group-levels-count">No active plans available</div>';
                echo '<div class="group-description">New plans coming soon</div>';
                echo '<button class="orabooks-btn orabooks-btn-secondary" disabled>Coming Soon</button>';
            }
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    } else {
        echo '<div class="orabooks-no-groups">';
        echo '<p>No membership groups available at the moment.</p>';
        echo '</div>';
    }
}

function orabooks_display_group_levels( $group_id, $symbol = '৳', $pos = 'after' ) {
    global $wpdb;
    
    orabooks_handle_multisite_tables();
    $group = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->orabooks_groups} WHERE id = %d", $group_id ) );
    if ( ! $group ) {
        echo '<p>Group not found.</p>';
        return;
    }
    
    $back_url = remove_query_arg( 'orabooks_group' );
    echo '<a href="' . esc_url( $back_url ) . '" class="orabooks-back-link">← Back to All Groups</a>';
    echo '<div class="orabooks-group-header">';
    echo '<h2>' . esc_html( $group->name ) . ' Membership Plans</h2>';
    if ( ! empty( $group->description ) ) {
        echo '<p class="group-description">' . esc_html( $group->description ) . '</p>';
    }
    echo '</div>';
    
    $levels = $wpdb->get_results( $wpdb->prepare( "
        SELECT * FROM {$wpdb->orabooks_levels} 
        WHERE group_id = %d AND is_active = 1
        ORDER BY price ASC
    ", $group_id ) );
    
    if ( $levels ) {
        echo '<div class="orabooks-levels-grid">';
        foreach ( $levels as $l ) {
            // Use hardcoded BDT symbol and position
            $price = number_format( $l->price, 2 );
            $display = $price . '৳';
            
            $billing_text = '';
            if ( $l->billing_period === 'one-time' ) {
                $billing_text = 'One Time Payment';
            } elseif ( $l->billing_period === 'free' ) {
                $billing_text = 'Free Forever';
            } elseif ( $l->billing_period === 'lifetime' ) {
                $billing_text = 'Lifetime Access';
            } else {
                $billing_text = 'per ' . $l->billing_period;
            }
            
            echo '<div class="orabooks-level-card ' . ( $l->price == 0 ? 'free-plan' : ( $l->price >= 100 ? 'premium-plan' : '' ) ) . '">';
            echo '<div class="level-header">';
            echo '<h3>' . esc_html( $l->name ) . '</h3>';
            // If admin provided a label, show it. Otherwise fall back to price heuristics.
            if ( ! empty( $l->label ) ) {
                echo '<span class="popular-badge">' . esc_html( $l->label ) . '</span>';
            } else {
                if ( $l->price == 0 ) {
                    echo '<span class="popular-badge">Free</span>';
                } elseif ( $l->price >= 100 ) {
                    echo '<span class="popular-badge">Popular</span>';
                }
            }
            echo '</div>';
            
            if ( ! empty( $l->description ) ) {
                echo '<div class="level-description">' . esc_html( $l->description ) . '</div>';
            }
            
            echo '<div class="level-price">';
            echo '<span class="price-amount">' . $display . '</span>';
            echo '<span class="billing-period">' . $billing_text . '</span>';
            echo '</div>';
            
            // Ensure checkout page exists
            $checkout_page = orabooks_get_or_create_page('Orabooks Checkout', '[orabooks_checkout]');
            
            // Show button for all plans
            if ($l->price > 0) {
                // Paid plans go to checkout
                $url = $checkout_page ? add_query_arg( array( 'join_level' => $l->id ), get_permalink( $checkout_page->ID ) ) : '#';
                $button_text = ( $l->price >= 100 ) ? 'Get Premium' : 'Choose Plan';
                $button_class = ( $l->price >= 100 ) ? 'orabooks-btn-premium' : 'orabooks-btn-primary';
                
                echo '<a href="' . esc_url( $url ) . '" class="orabooks-btn ' . $button_class . '">' . $button_text . '</a>';
            } else {
                // Free plans get activated directly
                $activation_url = add_query_arg( array( 
                    'action' => 'orabooks_activate_free_plan',
                    'level_id' => $l->id,
                    'nonce' => wp_create_nonce('orabooks_activate_free_plan_' . $l->id)
                ), get_permalink() );
                
                echo '<a href="' . esc_url( $activation_url ) . '" class="orabooks-btn orabooks-btn-success">Activate Free Plan</a>';
            }
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="orabooks-no-levels">';
        echo '<p>No active levels available in this group yet.</p>';
        echo '</div>';
    }
}

// Checkout shortcode
function orabooks_checkout_shortcode() {
    // Ensure confirmation page exists
    $confirmation_page = orabooks_get_or_create_page('Orabooks Confirmation', '[orabooks_confirmation]');
    
    // Display checkout form if level is selected
    if ( isset( $_GET['join_level'] ) ) {
        $level_id = intval( $_GET['join_level'] );
        global $wpdb;
        
        orabooks_handle_multisite_tables();
        $level = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->orabooks_levels} WHERE id=%d AND is_active=1", $level_id ) );
        
        if ( ! $level ) {
            return '<div class="orabooks-checkout-error"><p>Level not found or not available.</p></div>';
        }
        
        $symbol = '৳';
        $pos = 'after';
        $price = number_format( $level->price, 2 );
        $display = $price . '৳';
        
        ob_start();
        ?>
        <div class="orabooks-checkout-container">
            <div class="orabooks-checkout-header">
                <h2>Complete Your Purchase</h2>
                <p>You're about to join: <strong><?php echo esc_html( $level->name ); ?></strong></p>
            </div>
            
            <div class="orabooks-checkout-details">
                <div class="checkout-summary">
                    <h3>Order Summary</h3>
                    <div class="summary-item">
                        <span>Plan:</span>
                        <span><?php echo esc_html( $level->name ); ?></span>
                    </div>
                    <div class="summary-item">
                        <span>Price:</span>
                        <span class="price"><?php echo $display; ?></span>
                    </div>
                    <div class="summary-item">
                        <span>Billing:</span>
                        <span><?php echo esc_html( ucfirst( $level->billing_period ) ); ?></span>
                    </div>
                    <?php if ( ! empty( $level->description ) ) : ?>
                    <div class="summary-description">
                        <p><?php echo esc_html( $level->description ); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="checkout-action">
                    <?php if ( is_user_logged_in() ) : ?>
                        <?php if ( $level->price == 0 ) : ?>
                            <!-- Free plan - direct assignment -->
                            <form method="post" id="free-checkout-form">
                                <?php wp_nonce_field( 'orabooks_free_checkout', 'orabooks_nonce' ); ?>
                                <input type="hidden" name="level_id" value="<?php echo intval( $level->id ); ?>">
                                <input type="hidden" name="action" value="orabooks_free_checkout">
                                <button type="submit" class="orabooks-btn orabooks-btn-primary orabooks-btn-large">
                                    Activate Free Plan
                                </button>
                            </form>
                        <?php else : ?>
                            <!-- Paid plan - AJAX checkout -->
                            <div class="payment-gateways-selection">
                                <h3>Select Payment Method</h3>
                                <div class="gateway-options">
                                    <?php
                                    $gateways = orabooks_init_payment_gateways();
                                    $first = true;

                                    // Determine client subdomain if available (from session or current host)
                                    $client_subdomain = '';
                                    $sess_subdomain = OraBooks_Session::get_instance()->get('orabooks_current_subdomain');
                                    if ( ! empty( $sess_subdomain ) ) {
                                        $client_subdomain = sanitize_text_field( $sess_subdomain );
                                    } else {
                                        $host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
                                        $main = parse_url( get_site_url(), PHP_URL_HOST );
                                        if ( $host && $main && strpos( $host, $main ) !== false ) {
                                            $parts = explode( '.', $host );
                                            if ( count( $parts ) > 2 ) {
                                                $client_subdomain = sanitize_text_field( $parts[0] );
                                            }
                                        }
                                    }

                                    foreach ( $gateways as $gateway_id => $gateway ) {
                                        // Always show ShurjoPay in the list; otherwise show gateways that report available.
                                        if ( $gateway->is_available() || strtolower( $gateway_id ) === 'shurjopay' || stripos( $gateway->get_title(), 'shurjo' ) !== false ) {
                                            $checked = $first ? 'checked' : '';

                                            // If this is the ShurjoPay gateway, append client subdomain to its label when available
                                            $title = $gateway->get_title();
                                            if ( strtolower( $gateway_id ) === 'shurjopay' || stripos( $gateway->get_title(), 'shurjo' ) !== false ) {
                                                if ( ! empty( $client_subdomain ) ) {
                                                    $title = $title . ' (' . esc_html( $client_subdomain ) . ')';
                                                }
                                            }

                                            echo '<label class="gateway-option">'
                                                . '<input type="radio" name="payment_gateway" value="' . esc_attr( $gateway_id ) . '" ' . $checked . '>'
                                                . '<span class="gateway-logo">' . esc_html( $title ) . '</span>'
                                                . '<span class="gateway-desc">' . esc_html( $gateway->get_description() ) . '</span>'
                                            . '</label>';
                                            $first = false;
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            <button type="button" class="orabooks-btn orabooks-btn-primary orabooks-btn-large" id="complete-purchase-btn" data-level-id="<?php echo intval( $level->id ); ?>">
                                Complete Purchase - <?php echo $display; ?>
                            </button>
                            <p class="checkout-note">Secure payment processing</p>
                            
                            <script type="text/javascript">
                            jQuery(document).ready(function($) {
                                $('#complete-purchase-btn').on('click', function() {
                                    var btn = $(this);
                                    var levelId = btn.data('level-id');
                                    var gateway = $('input[name="payment_gateway"]:checked').val();
                                    
                                    if (!gateway) {
                                        alert('Please select a payment method');
                                        return;
                                    }
                                    
                                    btn.prop('disabled', true).text('Processing Payment...');
                                    
                                    console.log('Sending AJAX payment request:', {
                                        levelId: levelId,
                                        gateway: gateway
                                    });
                                    
                                    $.ajax({
                                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                                        type: 'POST',
                                        data: {
                                            action: 'orabooks_process_payment',
                                            level_id: levelId,
                                            gateway: gateway,
                                            nonce: '<?php echo wp_create_nonce('orabooks_payment_nonce'); ?>'
                                        },
                                        success: function(response) {
                                            console.log('AJAX success response:', response);
                                            if (response.success) {
                                                // Show redirect URL as a fallback/sanity check for debugging
                                                var redirectUrl = response.data && response.data.redirect_url ? response.data.redirect_url : '';
                                                if (redirectUrl) {
                                                    console.info('Redirecting to:', redirectUrl);
                                                    // Redirect to gateway URL
                                                    window.location.href = redirectUrl;
                                                } else {
                                                    console.warn('Payment succeeded but no redirect URL was returned.');
                                                    btn.prop('disabled', false).text('Complete Purchase - <?php echo $display; ?>');
                                                }
                                            } else {
                                                var msg = response.data ? response.data : 'Unknown error';
                                                    console.error('Payment error:', msg);
                                                    alert('Payment Error: ' + msg);
                                                    btn.prop('disabled', false).text('Complete Purchase - <?php echo $display; ?>');
                                            }
                                        },
                                        error: function(xhr, status, error) {
                                            console.error('AJAX error:', {
                                                status: status,
                                                error: error,
                                                xhr: xhr,
                                                responseText: xhr.responseText
                                            });
                                            console.error('Network error. Please try again. Status:', status);
                                            alert('Network/Server Error: ' + error);
                                            btn.prop('disabled', false).text('Complete Purchase - <?php echo $display; ?>');
                                        }
                                    });
                                });
                            });
                            </script>
                        <?php endif; ?>
                        
                    <?php else : ?>
                        <div class="checkout-login-required">
                            <p>Please <a href="<?php 
                                $login_page = orabooks_get_or_create_page('Login', '[login_widget]');
                                echo esc_url( $login_page ? get_permalink( $login_page->ID ) : wp_login_url( get_permalink() ) ); 
                            ?>">log in</a> to complete your purchase.</p>
                            <p>Don't have an account? <a href="<?php 
                                $register_page = orabooks_get_or_create_page('Orabooks Register', '[orabooks_register]');
                                echo esc_url( $register_page ? get_permalink( $register_page->ID ) : wp_registration_url() ); 
                            ?>">Register here</a></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    return '<div class="orabooks-checkout-empty"><p>Please choose a plan from the Pricing page to continue.</p></div>';
}

// Handle free checkout form submission
add_action('init', 'orabooks_handle_free_checkout');
function orabooks_handle_free_checkout() {
    if (isset($_POST['action']) && $_POST['action'] === 'orabooks_free_checkout' && isset($_POST['level_id'])) {
        if (!isset($_POST['orabooks_nonce']) || !wp_verify_nonce($_POST['orabooks_nonce'], 'orabooks_free_checkout')) {
            wp_die('Security check failed');
        }
        
        if (!is_user_logged_in()) {
            wp_die('You must be logged in');
        }
        
        $level_id = isset($_POST['level_id']) ? intval($_POST['level_id']) : 0;
        $user_id = get_current_user_id();
        
        // Verify it's actually a free level
        global $wpdb;
        orabooks_handle_multisite_tables();
        $level = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->orabooks_levels} WHERE id=%d AND price=0 AND is_active=1", $level_id));
        
        if (!$level) {
            wp_die('Invalid free level');
        }
        
        // Create order record
        $order_id = 'FREE' . time() . rand(1000, 9999);
        $order_data = array(
            'order_id' => $order_id,
            'user_id' => $user_id,
            'level_id' => $level_id,
            'amount' => 0,
            'gateway' => 'free',
            'status' => 'completed',
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert($wpdb->orabooks_orders, $order_data);
        $order_row_id = $wpdb->insert_id;
        
        // Trigger order completion
        do_action('orabooks_order_completed', $order_row_id, (object)$order_data);
        
        // Redirect to confirmation
        $confirmation_page = orabooks_get_or_create_page('Orabooks Confirmation', '[orabooks_confirmation]');
        $redirect_url = $confirmation_page ? add_query_arg(array('purchase' => 'success'), get_permalink($confirmation_page->ID)) : home_url();
        wp_redirect($redirect_url);
        exit;
    }
}

// My Account shortcode
function orabooks_account_shortcode() {
    if ( ! is_user_logged_in() ) {
        $login_page = orabooks_get_or_create_page('Login', '[login_widget]');
        return '<div class="login-required">
            <p>Please <a href="' . ($login_page ? get_permalink( $login_page->ID ) : wp_login_url( get_permalink() )) . '">log in</a> to view your account.</p>
        </div>';
    }
    
    $user_id = get_current_user_id();
    global $wpdb;
    orabooks_handle_multisite_tables();
    $level_id = get_user_meta( $user_id, 'orabooks_level', true );
    $level = $level_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->orabooks_levels} WHERE id=%d", $level_id ) ) : null;
    $group_name = $level ? $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->orabooks_groups} WHERE id=%d", $level->group_id ) ) : '';
    
    // Use hardcoded BDT currency
    $symbol = '৳';
    $pos = 'after';
    
    $out = '';
    
    // Handle profile update
    if ( isset( $_POST['orabooks_update_profile'] ) ) {
        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'orabooks_edit_profile' ) ) {
            $out .= '<div class="orabooks-error"><p>Security check failed.</p></div>';
        } else {
                $customer_address = sanitize_text_field( $_POST['customer_address'] ?? '' );
            $customer_city = sanitize_text_field( $_POST['customer_city'] ?? '' );
            $customer_phone = sanitize_text_field( $_POST['customer_phone'] ?? '' );
            $errors = new WP_Error();

            // Validate fields
            if ( empty( $customer_address ) ) $errors->add( 'no_address', 'Address is required' );
            if ( empty( $customer_city ) ) $errors->add( 'no_city', 'City is required' );
            if ( empty( $customer_phone ) ) $errors->add( 'no_phone', 'Phone is required' );
            if ( ! empty( $customer_phone ) && strlen( preg_replace('/\D/', '', $customer_phone) ) < 9 ) {
                $errors->add( 'short_phone', 'Phone must be at least 9 digits' );
            }

            if ( ! $errors->has_errors() ) {
                update_user_meta( $user_id, 'billing_address_1', $customer_address );
                update_user_meta( $user_id, 'billing_city', $customer_city );
                update_user_meta( $user_id, 'billing_phone', $customer_phone );
                update_user_meta( $user_id, 'phone', $customer_phone );
                $out .= '<div class="orabooks-success"><p>Your profile has been updated successfully.</p></div>';
            } else {
                $error_messages = array();
                $error_codes = $errors->get_error_codes();
                foreach ($error_codes as $code) {
                    $message = $errors->get_error_message($code);
                    $error_messages[] = esc_html($message);
                }
                $out .= '<div class="orabooks-error"><p><strong>Update Error:</strong></p><ul>';
                foreach ($error_messages as $msg) {
                    $out .= '<li>' . $msg . '</li>';
                }
                $out .= '</ul></div>';
            }
        }
    }
    
    // Get current user meta (with fallbacks)
    $customer_address = get_user_meta( $user_id, 'billing_address_1', true ) ?? '';
    $customer_city = get_user_meta( $user_id, 'billing_city', true ) ?? '';
    $customer_phone = get_user_meta( $user_id, 'billing_phone', true ) ?? '';
    
    ob_start();
    ?>
    <div class="orabooks-account-container">
        <div class="orabooks-account-header">
            <h2>My Account</h2>
            <p>Manage your membership and account settings</p>
        </div>
        
        <?php echo $out; ?>
        
        <div class="orabooks-account-content">
            <div class="account-section account-profile">
                <h3>Profile Information</h3>
                <div class="profile-details">
                    <?php $user = wp_get_current_user(); ?>
                    <div class="profile-item">
                        <label>Name:</label>
                        <span><?php echo esc_html( $user->display_name ); ?></span>
                    </div>
                    <div class="profile-item">
                        <label>Email:</label>
                        <span><?php echo esc_html( $user->user_email ); ?></span>
                    </div>
                    <div class="profile-item">
                        <label>Member Since:</label>
                        <span><?php echo date( 'F j, Y', strtotime( $user->user_registered ) ); ?></span>
                    </div>
                </div>

                <div class="account-upgrade-plans-inner" style="margin-top: 1rem; border-top: 1px solid var(--orabooks-border-light); padding-top: 1rem;">
                    <h4 style="font-size: 1rem; margin-bottom: 0.5rem; text-align: center;">Upgrade or Change Plan</h4>
                    <p style="font-size: 0.85rem; color: var(--orabooks-text-light); margin-bottom: 0.5rem; text-align: center;">View available membership plans and upgrade your subscription:</p>
                    <?php 
                    $pricing_page = get_page_by_path('upgrade-plan');
                    if (!$pricing_page) {
                        $pricing_page = orabooks_get_or_create_page('Orabooks Pricing', '[orabooks_levels]');
                    }
                    if ( $pricing_page ) : 
                    ?>
                    <a href="<?php echo esc_url( get_permalink( $pricing_page->ID ) ); ?>" class="orabooks-btn orabooks-btn-primary" target="_blank">
                        View Plans
                    </a>
                    <?php endif; ?>
                </div>
                
                <div class="account-features-inner" style="margin-top: 1rem; border-top: 1px solid var(--orabooks-border-light); padding-top: 1rem;">
                    <h4 style="font-size: 1rem; margin-bottom: 0.5rem; text-align: center;">My Features</h4>
                    <p style="font-size: 0.85rem; color: var(--orabooks-text-light); margin-bottom: 0.5rem; text-align: center;">Access your purchased feature addons:</p>
                    <?php
                    $features_url = home_url('/features');
                    $fp = get_page_by_path('features');
                    if ($fp) {
                        $features_url = get_permalink($fp->ID);
                    }
                    ?>
                    <div style="text-align: center;">
                        <a href="<?php echo esc_url($features_url); ?>" class="orabooks-btn orabooks-btn-secondary">Browse Features</a>
                    </div>
                </div>
            </div>
            
            <div class="account-section account-edit-profile">
                <h3>Edit Profile & Shipping Information</h3>
                <form method="post" class="orabooks-edit-profile-form">
                    <?php wp_nonce_field( 'orabooks_edit_profile' ); ?>
                    
                    <!-- Order Prefix removed from user-editable profile. Managed in Settings > Payment Gateway > ShurjoPay -->

                    <div class="form-group">
                        <label for="customer_address">Address</label>
                        <input type="text" id="customer_address" name="customer_address" value="<?php echo esc_attr( $customer_address ); ?>" required class="form-control" placeholder="Street address">
                    </div>

                    <div class="form-group">
                        <label for="customer_city">City</label>
                        <input type="text" id="customer_city" name="customer_city" value="<?php echo esc_attr( $customer_city ); ?>" required class="form-control" placeholder="City">
                    </div>

                    <div class="form-group">
                        <label for="customer_phone">Phone</label>
                        <input type="tel" id="customer_phone" name="customer_phone" value="<?php echo esc_attr( $customer_phone ); ?>" required class="form-control" minlength="9" placeholder="01XXXXXXXXX">
                    </div>

                    <button type="submit" name="orabooks_update_profile" class="orabooks-btn orabooks-btn-primary">
                        Update Profile
                    </button>
                </form>
            </div>
            
            <div class="account-section account-membership">
                <h3>Membership Details</h3>
                <?php if ( $level ) : ?>
                    <div class="membership-details">
                        <div class="membership-item">
                            <label>Plan:</label>
                            <span class="plan-name"><?php echo esc_html( $level->name ); ?></span>
                        </div>
                        <div class="membership-item">
                            <label>Group:</label>
                            <span><?php echo esc_html( $group_name ); ?></span>
                        </div>
                        <div class="membership-item">
                            <label>Price:</label>
                            <span class="plan-price">
                                <?php
                                $price = number_format( $level->price, 2 );
                                echo ( $pos === 'before' ) ? $symbol . $price : $price . $symbol;
                                echo ' / ' . esc_html( $level->billing_period );
                                ?>
                            </span>
                        </div>
                        <?php if ( ! empty( $level->description ) ) : ?>
                        <div class="membership-item">
                            <label>Description:</label>
                            <span><?php echo esc_html( $level->description ); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php 
                    $features_page = orabooks_get_or_create_page('Features', '[orabooks_features]');
                    if ( $features_page ) : 
                        // Check if user already has a subdomain/site
                        $user_subdomain = get_user_meta( get_current_user_id(), 'orabooks_subdomain', true );
                        
                        if ( ! empty( $user_subdomain ) ) {
                            // User has a site - redirect to their site's features page
                            // Build URL to their site
                            $main_domain = defined('DOMAIN_CURRENT_SITE') ? DOMAIN_CURRENT_SITE : parse_url(home_url(), PHP_URL_HOST);
                            $site_url = 'https://' . $user_subdomain . '.' . $main_domain;
                            $workspace_url = $site_url;
                        } else {
                            // No site yet - redirect to signup to create one
                            $workspace_url = network_site_url( 'wp-signup.php' );
                        }
                    ?>
                    <div class="membership-actions">
                        <a href="<?php echo esc_url( $workspace_url ); ?>" class="orabooks-btn orabooks-btn-primary">
                            <?php echo ! empty( $user_subdomain ) ? 'Go to My Site' : 'Create My Site'; ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                <?php else : ?>
                    <div class="no-membership">
                        <p>You don't have an active membership plan.</p>
                        <?php 
                        // Check for client site Upgrade Plan page first
                        $pricing_page = get_page_by_path('upgrade-plan');
                        if (!$pricing_page) {
                            $pricing_page = orabooks_get_or_create_page('Orabooks Pricing', '[orabooks_levels]');
                        }
                        if ( $pricing_page ) : 
                        ?>
                        <a href="<?php echo esc_url( get_permalink( $pricing_page->ID ) ); ?>" class="orabooks-btn orabooks-btn-primary">
                            Browse Plans
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="account-section account-actions">
                <h3>Account Actions</h3>
                <p>Manage your account session:</p>
                <div class="action-buttons" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="orabooks-btn orabooks-btn-secondary">
                        Logout
                    </a>
                    <button type="button" class="orabooks-btn orabooks-btn-danger" id="orabooks-delete-account-trigger" style="background-color: #dc2626; color: white; border: none;">
                        Account Deletation
                    </button>
                </div>

                <!-- Account Deletion Modal Overlay -->
                <div id="orabooks-delete-modal-overlay" class="orabooks-modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
                    <div class="orabooks-modal-content" style="background: white; padding: 2rem; border-radius: 12px; max-width: 500px; width: 90%; position: relative; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
                        <h3 style="color: #dc2626; margin-top: 0;">⚠️ Warning: Permanent Data Loss</h3>
                        <p>Are you sure you want to delete your account? This action <strong>cannot be undone</strong>. You will lose all your current data, subscriptions, and access to all features immediately.</p>
                        
                        <div class="form-group" style="margin-top: 1.5rem;">
                            <label for="orabooks_delete_reason" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Why are you deleting your account?</label>
                            <textarea id="orabooks_delete_reason" class="form-control" placeholder="Please let us know your reason (optional)" style="width: 100%; height: 80px;"></textarea>
                        </div>
                        
                        <div class="modal-actions" style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end;">
                            <button type="button" class="orabooks-btn orabooks-btn-secondary" id="orabooks-close-delete-modal">Cancel</button>
                            <button type="button" class="orabooks-btn" id="orabooks-confirm-delete" style="background-color: #dc2626; color: white; border: none;">Delete Permanently</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Confirmation shortcode
function orabooks_confirmation_shortcode() {
    // Ensure features page exists
    $features_page = orabooks_get_or_create_page('Features', '[orabooks_features]');
    $account_page = orabooks_get_or_create_page('Orabooks My Account', '[orabooks_my_account]');
    
    // Check if user just completed a purchase
    if ( isset( $_GET['purchase'] ) && $_GET['purchase'] === 'success' ) {
        ob_start();
        ?>
        <div class="orabooks-confirmation-container">
            <div class="confirmation-header">
                <div class="confirmation-icon">✅</div>
                <h2>Thank You for Your Purchase!</h2>
                <p>Your payment has been processed successfully and your membership is now active.</p>
            </div>
            
            <?php if ( is_user_logged_in() ) : ?>
                <?php
                $user_id = get_current_user_id();
                $level_id = get_user_meta( $user_id, 'orabooks_level', true );
                $level = $level_id ? orabooks_get_level( $level_id ) : null;
                ?>
                
                <div class="confirmation-details">
                    <?php if ( $level ) : ?>
                        <div class="confirmation-item">
                            <h3>Membership Activated</h3>
                            <p>You now have access to: <strong><?php echo esc_html( $level->name ); ?></strong></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="confirmation-actions">
                        <?php if ( $features_page ) : 
                            // Check if user already has a subdomain/site
                            $user_subdomain = get_user_meta( get_current_user_id(), 'orabooks_subdomain', true );
                            
                            if ( ! empty( $user_subdomain ) ) {
                                // User has a site - redirect to their site
                                $main_domain = defined('DOMAIN_CURRENT_SITE') ? DOMAIN_CURRENT_SITE : parse_url(home_url(), PHP_URL_HOST);
                                $site_url = 'https://' . $user_subdomain . '.' . $main_domain;
                                $workspace_url = $site_url;
                            } else {
                                // No site yet - redirect to signup to create one
                                $workspace_url = network_site_url( 'wp-signup.php' );
                            }
                        ?>
                        <a href="<?php echo esc_url( $workspace_url ); ?>" class="orabooks-btn orabooks-btn-primary">
                            <?php echo ! empty( $user_subdomain ) ? 'Go to My Site' : 'Create My Site'; ?>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ( $account_page ) : ?>
                        <a href="<?php echo esc_url( get_permalink( $account_page->ID ) ); ?>" class="orabooks-btn orabooks-btn-secondary">
                            Go to My Account
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else : ?>
                <div class="confirmation-login">
                    <p>Please <a href="<?php 
                        $login_page = orabooks_get_or_create_page('Login', '[login_widget]');
                        echo esc_url( $login_page ? get_permalink( $login_page->ID ) : wp_login_url( get_permalink() ) ); 
                    ?>">log in</a> to access your account.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // Default message if accessed directly
    return '<div class="orabooks-confirmation-default">
        <h2>Order Confirmation</h2>
        <p>If you just completed a purchase, please wait to be redirected, or <a href="' . ($account_page ? esc_url( get_permalink( $account_page->ID ) ) : '#') . '">check your account</a>.</p>
    </div>';
}

// Register shortcode
function orabooks_register_shortcode() {
    if ( is_user_logged_in() ) {
        return '<div class="orabooks-already-logged-in">
            <p>You are already logged in.</p>
        </div>';
    }
    
    // Redirect to default WordPress signup system
    $signup_url = network_site_url( 'wp-signup.php' );
    
    // Check if we are already on the signup page to avoid infinite loops 
    // (though shortcodes shouldn't run on wp-signup.php usually)
    global $pagenow;
    if ($pagenow !== 'wp-signup.php') {
        wp_redirect($signup_url);
        exit;
    }

    return '<div class="orabooks-register-redirect">
        <p>Redirecting to registration... If you are not redirected, <a href="' . esc_url($signup_url) . '">click here</a>.</p>
    </div>';
}

// Login shortcode

// Redirect after login to client site dashboard
// DISABLED: wp-frontend-dashboard plugin handles this now
// add_filter('lwws_login_redirect', 'orabooks_login_redirect_to_client_dashboard', 20, 2);
function orabooks_login_redirect_to_client_dashboard($redirect_url, $user_id) {
    if (!$user_id) return $redirect_url;
    
    // Check if user has a site
    $site_url = function_exists('orabooks_get_user_site_url') ? orabooks_get_user_site_url($user_id) : false;
    
    if ($site_url) {
        // Redirect to client site dashboard
        return trailingslashit($site_url) . 'wp-admin/';
    }
    
    return $redirect_url;
}


// Smart Login/Logout shortcode
function orabooks_login_logout_shortcode() {
    if ( is_user_logged_in() ) {
        // User is logged in - show Logout interface
        $user = wp_get_current_user();
        
        ob_start();
        ?>
        <div class="orabooks-logout-container">
            <div class="orabooks-user-profile">
                <div class="orabooks-avatar">
                    <?php echo get_avatar( $user->ID, 64 ); ?>
                </div>
                <div class="orabooks-user-info">
                    <h3>Hello, <?php echo esc_html( $user->display_name ); ?></h3>
                    <p class="user-email"><?php echo esc_html( $user->user_email ); ?></p>
                </div>
            </div>
            
            <div class="orabooks-logout-actions">
                <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="orabooks-btn orabooks-btn-secondary">
                    Sign Out
                </a>
                
                <?php 
                // Link to My Account
                // We try to find a page with [orabooks_my_account]
                $account_page = null;
                $pages = get_pages();
                foreach ($pages as $p) {
                    if (has_shortcode($p->post_content, 'orabooks_my_account')) {
                        $account_page = $p;
                        break;
                    }
                }
                
                if ($account_page) {
                    echo '<a href="' . esc_url( get_permalink($account_page->ID) ) . '" class="orabooks-btn orabooks-btn-primary" style="margin-left: 10px;">My Account</a>';
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
        
    } else {
        // User is logged out - show custom login widget
        return do_shortcode('[login_widget]');
    }
}

// Features shortcode - ENHANCED WITH ACCESS CONTROL
function orabooks_features_shortcode() {
    // Check if user is logged in
    if ( ! is_user_logged_in() ) {
        // Use standard WP login URL which might be intercepted by the login widget plugin
        $login_url = wp_login_url( get_permalink() );
        return '<div class="orabooks-features-login-required">
            <div class="login-required-icon">🔒</div>
            <h3>Login Required</h3>
            <p>Please <a href="' . esc_url($login_url) . '">log in</a> to access your features.</p>
        </div>';
    }

    $user_id = get_current_user_id();
    $user_level = get_user_meta( $user_id, 'orabooks_level', true );
    
    // Check if user has a subscription
    if ( ! $user_level ) {
        if (is_multisite() && get_current_blog_id() != 1) {
            $pricing_url = home_url('/upgrade-plan');
        } else {
            $pricing_page = orabooks_get_or_create_page('Orabooks Pricing', '[orabooks_levels]');
            $pricing_url = $pricing_page ? esc_url( get_permalink( $pricing_page->ID ) ) : '#';
        }
        return '<div class="orabooks-no-subscription">
            <div class="no-subscription-icon">📊</div>
            <h3>No Active Subscription</h3>
            <p>Please subscribe to a plan to access features.</p>
            <p><a href="' . $pricing_url . '" class="orabooks-btn orabooks-btn-primary">View Pricing Plans</a></p>
        </div>';
    }

    // Check if user has completed site signup
    $has_site = function_exists('orabooks_user_has_site') ? orabooks_user_has_site($user_id) : false;
    
    if ( ! $has_site ) {
        // User has membership but hasn't created their site yet
        $signup_url = network_site_url('wp-signup.php');
        return '<div class="orabooks-needs-signup">
            <div class="needs-signup-icon">🚀</div>
            <h3>Complete Your Setup</h3>
            <p>You have an active membership! Complete your site setup to access all features.</p>
            <p><a href="' . esc_url($signup_url) . '" class="orabooks-btn orabooks-btn-primary">Create My Site Now</a></p>
        </div>';
    }

    // Get user subdomain
    $user_subdomain = get_user_meta( $user_id, 'orabooks_subdomain', true );
    $site_url = function_exists('orabooks_get_user_site_url') ? orabooks_get_user_site_url($user_id) : '';
    
    ob_start();
    ?>
    <div class="orabooks-features-container">
        <div class="orabooks-features-header">
            <h2>Your Features & Applications</h2>
            <p>Access all the tools included with your membership</p>
            <?php if ($site_url): ?>
                <p class="site-info">Your site: <a href="<?php echo esc_url($site_url); ?>" target="_blank"><?php echo esc_html($user_subdomain); ?></a></p>
            <?php endif; ?>
        </div>

        <div class="orabooks-features-grid">
            <?php
            // Get features from database or use sample features
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            $db_features = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->orabooks_feature_assignments} WHERE level_id = %d",
                $user_level
            ) );
            
            // If no database features, use sample features
            if ( empty( $db_features ) ) {
                $sample_features = array(
                    array(
                        'name' => 'Accounting Dashboard',
                        'description' => 'Manage your finances and track expenses',
                        'icon' => '📊',
                        'slug' => 'accounting'
                    ),
                    array(
                        'name' => 'Inventory System',
                        'description' => 'Manage your items, stocks and inventory',
                        'icon' => '📦',
                        'slug' => 'inventory'
                    ),
                    array(
                        'name' => 'Project Management',
                        'description' => 'Organize and track your projects',
                        'icon' => '📋',
                        'slug' => 'projects'
                    ),
                    array(
                        'name' => 'File Storage',
                        'description' => 'Secure cloud storage for your documents',
                        'icon' => '💾',
                        'slug' => 'storage'
                    )
                );
                
                foreach ( $sample_features as $feature ) {
                    // Build feature URL - redirect to user's site
                    $feature_url = add_query_arg('orabooks_feature', $feature['slug'], $site_url);
                    
                    echo '<div class="orabooks-feature-card">';
                    echo '<div class="feature-icon">' . $feature['icon'] . '</div>';
                    echo '<div class="feature-content">';
                    echo '<h3>' . esc_html( $feature['name'] ) . '</h3>';
                    echo '<p>' . esc_html( $feature['description'] ) . '</p>';
                    echo '</div>';
                    echo '<div class="feature-action">';
                    echo '<a href="' . esc_url( $feature_url ) . '" class="orabooks-btn orabooks-btn-primary">Access Feature</a>';
                    echo '</div>';
                    echo '</div>';
                }
            } else {
                // Display database features
                $available_features = orabooks_get_available_features();
                
                foreach ( $db_features as $feature ) {
                    $feature_key = $feature->feature_key;
                    $feature_info = isset($available_features[$feature_key]) ? $available_features[$feature_key] : array();
                    
                    $name = isset($feature_info['name']) ? $feature_info['name'] : $feature->feature_name;
                    $icon = isset($feature_info['icon']) ? $feature_info['icon'] : '📦';
                    $description = isset($feature_info['description']) ? $feature_info['description'] : '';
                    
                    // Build feature URL
                    $feature_url = add_query_arg('orabooks_feature', $feature_key, $site_url);
                    
                    echo '<div class="orabooks-feature-card">';
                    echo '<div class="feature-icon">' . $icon . '</div>';
                    echo '<div class="feature-content">';
                    echo '<h3>' . esc_html( $name ) . '</h3>';
                    if ($description) {
                        echo '<p>' . esc_html( $description ) . '</p>';
                    }
                    echo '<span class="feature-access-type">Access: ' . esc_html( ucfirst($feature->access_type) ) . '</span>';
                    echo '</div>';
                    echo '<div class="feature-action">';
                    echo '<a href="' . esc_url( $feature_url ) . '" class="orabooks-btn orabooks-btn-primary">Access Feature</a>';
                    echo '</div>';
                    echo '</div>';
                }
            }
            ?>
        </div>
    </div>

    <?php
    return ob_get_clean();
}


// Logout handler
add_action( 'template_redirect', 'orabooks_handle_logout' );
function orabooks_handle_logout() {
    if ( isset( $_GET['orabooks_action'] ) && $_GET['orabooks_action'] === 'logout' ) {
        if ( is_user_logged_in() ) {
            wp_logout();
        }
        wp_redirect( home_url() );
        exit;
    }
}

// Order completion hook
add_action( 'orabooks_order_completed', 'orabooks_on_order_completed', 10, 2 );
function orabooks_on_order_completed( $order_row_id, $order_row ) {
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    // Get the order data if not provided
    if ( ! is_object( $order_row ) ) {
        $order_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->orabooks_orders} WHERE id=%d", intval( $order_row_id ) ) );
    }
    
    if ( ! $order_row ) {
        return;
    }
    
    if ( $order_row->user_id && $order_row->level_id ) {
        // Update user's membership level
        update_user_meta( $order_row->user_id, 'orabooks_level', $order_row->level_id );
        
        // Create subscription
        $subscription_id = 'SUB' . time() . rand( 1000, 9999 );
        $subscription_data = array(
            'subscription_id' => $subscription_id,
            'user_id' => $order_row->user_id,
            'level_id' => $order_row->level_id,
            'gateway' => $order_row->gateway,
            'status' => 'active',
            'started_at' => current_time( 'mysql' ),
            'ends_at' => null
        );
        
        $wpdb->insert( $wpdb->orabooks_subscriptions, $subscription_data );
        
        // Auto setup workspace if not exists
        $existing_subdomain = get_user_meta( $order_row->user_id, 'orabooks_subdomain', true );
        if ( empty( $existing_subdomain ) ) {
            $user = get_userdata( $order_row->user_id );
            $subdomain_base = get_option( 'orabooks_subdomain_base', 'client' );
            $username_clean = sanitize_title( $user->user_login );
            $subdomain = $subdomain_base . '-' . $username_clean . '-' . wp_generate_password( 4, false );
            
            update_user_meta( $order_row->user_id, 'orabooks_subdomain', $subdomain );
            update_user_meta( $order_row->user_id, 'orabooks_workspace_setup', current_time( 'mysql' ) );
        }
    }
}

// Payment Success Shortcode
function orabooks_payment_success_shortcode() {
    // If we have a specific order ID, we could fetch details, but broadly just show success
    ob_start();
    ?>
    <div class="orabooks-confirmation-container">
        <div class="confirmation-header">
            <div class="confirmation-icon" style="font-size: 48px; margin-bottom: 20px;">✅</div>
            <h2>Payment Successful!</h2>
            <p>Thank you for your payment. Your transaction has been completed successfully.</p>
        </div>
        
        <div class="confirmation-actions" style="margin-top: 30px; text-align: center;">
            <a href="<?php echo esc_url( orabooks_get_feature_access_url() ); ?>" class="orabooks-btn orabooks-btn-primary">
                Access Dashboard
            </a>
            <?php 
            $account_page = orabooks_get_or_create_page('Orabooks My Account', '[orabooks_my_account]'); 
            if ($account_page):
            ?>
            <a href="<?php echo esc_url( get_permalink( $account_page->ID ) ); ?>" class="orabooks-btn orabooks-btn-secondary" style="margin-left: 10px;">
                My Account
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Payment Failed Shortcode
function orabooks_payment_failed_shortcode() {
    ob_start();
    ?>
    <div class="orabooks-confirmation-container orabooks-failed">
        <div class="confirmation-header">
            <div class="confirmation-icon" style="color: #dc3545; font-size: 48px; margin-bottom: 20px;">❌</div>
            <h2>Payment Failed</h2>
            <p>We were unable to process your payment. Please check your details and try again.</p>
        </div>
        
        <div class="confirmation-actions" style="margin-top: 30px; text-align: center;">
            <?php 
            $pricing_page = orabooks_get_or_create_page('Orabooks Pricing', '[orabooks_levels]'); 
            if ($pricing_page):
            ?>
            <a href="<?php echo esc_url( get_permalink( $pricing_page->ID ) ); ?>" class="orabooks-btn orabooks-btn-primary">
                Try Again
            </a>
            <?php endif; ?>
            
            <a href="<?php echo esc_url( home_url() ); ?>" class="orabooks-btn orabooks-btn-secondary" style="margin-left: 10px;">
                Back to Home
            </a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Upgrade Plan Shortcode
function orabooks_upgrade_plan_shortcode() {
    ob_start();
    
    // Show success message if free plan was just activated
    if (isset($_GET['free_plan_activated']) && $_GET['free_plan_activated'] === '1') {
        echo '<div class="orabooks-success-message" style="background: #28a745; color: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #28a745; text-align: center; font-size: 16px; box-shadow: 0 2px 10px rgba(40, 167, 69, 0.2);">';
        echo '<div style="font-size: 24px; margin-bottom: 10px;">🎉</div>';
        echo '<strong style="font-size: 18px;">Plan Successfully Activated!</strong><br>';
        echo '<span style="margin-top: 5px; display: block;">Your free plan has been activated. You can now access all the features included in your plan.</span>';
        echo '</div>';
    }
    
    ?>
    <div class="orabooks-upgrade-plan-container">
        <div class="upgrade-plan-header">
            <h2>Upgrade Your Plan</h2>
            <p>Choose the perfect plan for your needs and unlock more features.</p>
        </div>
        
        <div class="upgrade-plan-content">
            <?php
            // Display the levels/pricing table
            if (function_exists('orabooks_levels_shortcode')) {
                echo orabooks_levels_shortcode();
            } else {
                // Fallback if levels shortcode is not available
                echo '<div class="orabooks-notice">';
                echo '<p>Pricing plans are currently being updated. Please check back later.</p>';
                echo '</div>';
            }
            ?>
        </div>
        
        <div class="upgrade-plan-actions" style="margin-top: 30px; text-align: center;">
            <a href="<?php echo esc_url( orabooks_get_feature_access_url() ); ?>" class="orabooks-btn orabooks-btn-secondary">
                Back to Dashboard
            </a>
        </div>
    </div>
    
    <style>
    .orabooks-upgrade-plan-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .upgrade-plan-header {
        text-align: center;
        margin-bottom: 40px;
    }
    
    .upgrade-plan-header h2 {
        font-size: 2.5rem;
        margin-bottom: 10px;
        color: var(--orabooks-primary, #0073aa);
    }
    
    .upgrade-plan-header p {
        font-size: 1.2rem;
        color: var(--orabooks-text-light, #666);
    }
    
    .orabooks-notice {
        background: var(--orabooks-secondary, #f8f9fa);
        border: 1px solid var(--orabooks-border, #dee2e6);
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        margin: 20px 0;
    }
    
    @media (max-width: 768px) {
        .upgrade-plan-header h2 {
            font-size: 2rem;
        }
        
        .upgrade-plan-header p {
            font-size: 1rem;
        }
    }
    </style>
    <?php
    return ob_get_clean();
}

// Additional shortcodes from old plugin structure
add_shortcode('forgot_password', 'forgot_password_shortcode');
add_shortcode('orabooks_accounting', 'orabooks_accounting_shortcode');
add_shortcode('orabooks_inventory', 'orabooks_inventory_shortcode');

function forgot_password_shortcode() {
    ob_start();
    
    $message = '';
    $message_type = '';
    
    if (isset($_POST['reset_password']) && wp_verify_nonce($_POST['reset_password_nonce'] ?? '', 'reset_password')) {
        $user_login = sanitize_text_field($_POST['user_login'] ?? '');
        
        if (empty($user_login)) {
            $message = 'Please enter your username or email address.';
            $message_type = 'error';
        } else {
            $user = get_user_by('login', $user_login) ?: get_user_by('email', $user_login);
            
            if (!$user) {
                $message = 'No account found with that username or email.';
                $message_type = 'error';
            } else {
                $reset_key = get_password_reset_key($user);
                
                if (is_wp_error($reset_key)) {
                    $message = 'Unable to generate password reset link. Please try again.';
                    $message_type = 'error';
                } else {
                    $reset_url = network_site_url("login/?action=rp&key=$reset_key&login=" . rawurlencode($user->user_login));
                    
                    $subject = 'Password Reset Request';
                    $message_body = "Hello {$user->display_name},\n\n";
                    $message_body .= "You requested a password reset for your account.\n\n";
                    $message_body .= "Click here to reset your password: $reset_url\n\n";
                    $message_body .= "If you didn't request this, please ignore this email.\n\n";
                    $message_body .= "Best regards,\nThe Team";
                    
                    $sent = wp_mail($user->user_email, $subject, $message_body);
                    
                    if ($sent) {
                        $message = 'Password reset link has been sent to your email address.';
                        $message_type = 'success';
                    } else {
                        $message = 'Unable to send reset email. Please try again.';
                        $message_type = 'error';
                    }
                }
            }
        }
    }
    ?>
    <div class="orabooks-forgot-password" style="max-width: 480px; margin: 40px auto; padding: 30px; background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <h2 style="text-align: center; margin-bottom: 10px; color: #333;">Forgot Password</h2>
        <p style="text-align: center; color: #666; margin-bottom: 25px;">Enter your email to reset your password</p>
        
        <?php if ($message): ?>
            <div style="padding: 12px; border-radius: 6px; margin-bottom: 20px; <?php echo $message_type === 'success' ? 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'; ?>">
                <?php echo esc_html($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="post" style="display: flex; flex-direction: column; gap: 15px;">
            <input type="text" name="user_login" required placeholder="Username or Email Address" style="padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px;">
            <?php wp_nonce_field('reset_password', 'reset_password_nonce'); ?>
            <input type="hidden" name="reset_password" value="1">
            <button type="submit" style="padding: 12px; background: #43a62d; color: white; border: none; border-radius: 6px; font-size: 16px; cursor: pointer;">Send Reset Link</button>
        </form>
        
        <p style="text-align: center; margin-top: 20px;">
            <a href="<?php echo wp_login_url(); ?>" style="color: #43a62d;">Back to Login</a>
        </p>
    </div>
    <?php
    return ob_get_clean();
}

function orabooks_accounting_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please <a href="' . wp_login_url(get_permalink()) . '">login</a> to access accounting.</p>';
    }
    return '<div class="orabooks-module-area"><h3>Accounting Dashboard</h3><p>Your accounting module will be available here.</p></div>';
}

function orabooks_inventory_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please <a href="' . wp_login_url(get_permalink()) . '">login</a> to access inventory.</p>';
    }
    return '<div class="orabooks-module-area"><h3>Inventory Dashboard</h3><p>Your inventory module will be available here.</p></div>';
}