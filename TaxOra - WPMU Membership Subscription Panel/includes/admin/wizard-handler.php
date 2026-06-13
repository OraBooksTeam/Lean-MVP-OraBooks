<?php
/**
 * Orabooks Membership Setup Wizard Handler
 * Handles wizard logic, redirects, and data saving
 */

// Add wizard menu item
add_action( 'admin_menu', 'orabooks_wizard_menu' );
function orabooks_wizard_menu() {
    add_submenu_page(
        null, // Hidden from menu
        'Setup Wizard',
        'Setup Wizard',
        'manage_options',
        'orabooks-wizard',
        'orabooks_wizard_page'
    );
}

// Check if wizard is completed
function orabooks_is_wizard_completed() {
    return get_option( 'orabooks_wizard_completed', false );
}

// Get current wizard step
function orabooks_get_wizard_step() {
    if ( ! empty( $_REQUEST['step'] ) ) {
        return sanitize_text_field( $_REQUEST['step'] );
    }
    
    $saved_step = get_option( 'orabooks_wizard_step' );
    if ( ! empty( $saved_step ) ) {
        return $saved_step;
    }
    
    return 'general';
}

// Block admin access if wizard not completed
add_action( 'admin_init', 'orabooks_check_wizard_completion' );
function orabooks_check_wizard_completion() {
    // Don't block if on wizard page or AJAX
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'orabooks-wizard' ) {
        return;
    }
    
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
        return;
    }
    
    // Don't block if wizard is completed
    if ( orabooks_is_wizard_completed() ) {
        return;
    }
    
    // Check if user is trying to access any orabooks admin page
    $current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
    
    if ( strpos( $current_page, 'orabooks-membership' ) === 0 ) {
        // Redirect to wizard
        wp_safe_redirect( admin_url( 'admin.php?page=orabooks-wizard' ) );
        exit;
    }
}

// Handle wizard form submissions
add_action( 'admin_init', 'orabooks_save_wizard_data' );
function orabooks_save_wizard_data() {
    // Only process on wizard page
    if ( empty( $_REQUEST['page'] ) || $_REQUEST['page'] !== 'orabooks-wizard' ) {
        return;
    }
    
    // Handle done step
    if ( ! empty( $_REQUEST['step'] ) && $_REQUEST['step'] === 'done' ) {
        update_option( 'orabooks_wizard_completed', true );
        update_option( 'orabooks_wizard_step', 'done' );
        return;
    }
    
    // Only process on form submission
    if ( empty( $_REQUEST['submit'] ) ) {
        return;
    }
    
    // Step 1: General Info
    if ( isset( $_REQUEST['wizard-action'] ) && $_REQUEST['wizard-action'] === 'step-1' ) {
        if ( ! wp_verify_nonce( sanitize_key( $_REQUEST['orabooks_wizard_step_1_nonce'] ), 'orabooks_wizard_step_1_nonce' ) ) {
            return;
        }
        
        // Save general settings
        if ( ! empty( $_REQUEST['site_name'] ) ) {
            update_option( 'orabooks_site_name', sanitize_text_field( $_REQUEST['site_name'] ) );
        }
        
        if ( ! empty( $_REQUEST['site_description'] ) ) {
            update_option( 'orabooks_site_description', sanitize_textarea_field( $_REQUEST['site_description'] ) );
        }
        
        // Save subscriber type
        if ( ! empty( $_REQUEST['subscriber_type'] ) ) {
            $subscriber_type = sanitize_text_field( $_REQUEST['subscriber_type'] );
            if ( in_array( $subscriber_type, array( 'agent', 'individual' ) ) ) {
                update_option( 'orabooks_subscriber_type', $subscriber_type );
            }
        }
        
        if ( ! empty( $_REQUEST['collect_payments'] ) ) {
            update_option( 'orabooks_collect_payments', true );
            $next_step = 'payments';
        } else {
            update_option( 'orabooks_collect_payments', false );
            $next_step = 'groups';
        }
        
        // Create pages if requested
        if ( ! empty( $_REQUEST['create_pages'] ) ) {
            orabooks_create_wizard_pages();
        }
        
        update_option( 'orabooks_wizard_step', $next_step );
        wp_safe_redirect( admin_url( 'admin.php?page=orabooks-wizard&step=' . $next_step ) );
        exit;
    }
    
    // Step 2: Payments
    if ( isset( $_REQUEST['wizard-action'] ) && $_REQUEST['wizard-action'] === 'step-2' ) {
        if ( ! wp_verify_nonce( sanitize_key( $_REQUEST['orabooks_wizard_step_2_nonce'] ), 'orabooks_wizard_step_2_nonce' ) ) {
            return;
        }
        
        // Save payment gateway preference
        if ( ! empty( $_REQUEST['payment_gateway'] ) ) {
            update_option( 'orabooks_payment_gateway', sanitize_text_field( $_REQUEST['payment_gateway'] ) );
        }
        
        update_option( 'orabooks_wizard_step', 'groups' );
        wp_safe_redirect( admin_url( 'admin.php?page=orabooks-wizard&step=groups' ) );
        exit;
    }
    
    // Step 3: Groups
    if ( isset( $_REQUEST['wizard-action'] ) && $_REQUEST['wizard-action'] === 'step-3' ) {
        if ( ! wp_verify_nonce( sanitize_key( $_REQUEST['orabooks_wizard_step_3_nonce'] ), 'orabooks_wizard_step_3_nonce' ) ) {
            return;
        }
        
        global $wpdb;
        orabooks_handle_multisite_tables();
        
        if ( ! empty( $_REQUEST['group_name'] ) ) {
            $wpdb->insert(
                $wpdb->orabooks_groups,
                array(
                    'name' => sanitize_text_field( $_REQUEST['group_name'] ),
                    'description' => sanitize_textarea_field( $_REQUEST['group_description'] ),
                    'created_at' => current_time( 'mysql' )
                ),
                array( '%s', '%s', '%s' )
            );
        }
        
        update_option( 'orabooks_wizard_step', 'levels' );
        wp_safe_redirect( admin_url( 'admin.php?page=orabooks-wizard&step=levels' ) );
        exit;
    }
    
    // Step 4: Levels
    if ( isset( $_REQUEST['wizard-action'] ) && $_REQUEST['wizard-action'] === 'step-4' ) {
        if ( ! wp_verify_nonce( sanitize_key( $_REQUEST['orabooks_wizard_step_4_nonce'] ), 'orabooks_wizard_step_4_nonce' ) ) {
            return;
        }
        
        global $wpdb;
        
        // Ensure table names are set
        orabooks_handle_multisite_tables();
        
        // Get the last created group ID to associate with levels
        $group_id = $wpdb->get_var( "SELECT id FROM $wpdb->orabooks_groups ORDER BY id DESC LIMIT 1" );
        
        // Create free level if requested
        if ( ! empty( $_REQUEST['create_free_level'] ) ) {
            $free_level_name = ! empty( $_REQUEST['free_level_name'] ) ? sanitize_text_field( $_REQUEST['free_level_name'] ) : 'Free';
            
            $wpdb->insert(
                $wpdb->orabooks_levels,
                array(
                    'name' => $free_level_name,
                    'group_id' => $group_id,
                    'price' => 0,
                    'billing_period' => 'monthly',
                    'is_active' => 1,
                    'currency' => 'BDT',
                    'currency_symbol' => '৳',
                    'created_at' => current_time( 'mysql' )
                ),
                array( '%s', '%d', '%f', '%s', '%d', '%s', '%s', '%s' )
            );
        }
        
        // Create paid level if requested
        if ( ! empty( $_REQUEST['create_paid_level'] ) ) {
            $paid_level_name = ! empty( $_REQUEST['paid_level_name'] ) ? sanitize_text_field( $_REQUEST['paid_level_name'] ) : 'Premium';
            $amount = ! empty( $_REQUEST['paid_level_amount'] ) ? floatval( $_REQUEST['paid_level_amount'] ) : 0;
            $period = ! empty( $_REQUEST['billing_period'] ) ? sanitize_text_field( $_REQUEST['billing_period'] ) : 'monthly';
            
            $wpdb->insert(
                $wpdb->orabooks_levels,
                array(
                    'name' => $paid_level_name,
                    'group_id' => $group_id,
                    'price' => $amount,
                    'billing_period' => $period,
                    'is_active' => 1,
                    'currency' => 'BDT',
                    'currency_symbol' => '৳',
                    'created_at' => current_time( 'mysql' )
                ),
                array( '%s', '%d', '%f', '%s', '%d', '%s', '%s', '%s' )
            );
        }
        
        update_option( 'orabooks_wizard_step', 'done' );
        wp_safe_redirect( admin_url( 'admin.php?page=orabooks-wizard&step=done' ) );
        exit;
    }
}

// Create wizard pages
function orabooks_create_wizard_pages() {
    $pages = array(
        'pricing' => array(
            'title' => 'Orabooks Pricing',
            'content' => '[orabooks_levels]',
            'slug' => 'orabooks-pricing'
        ),
        'account' => array(
            'title' => 'Orabooks My Account',
            'content' => '[orabooks_my_account]',
            'slug' => 'orabooks-my-account'
        ),
        'login' => array(
            'title' => 'Login',
            'content' => '[login_widget]',
            'slug' => 'login'
        ),
        'register' => array(
            'title' => 'Orabooks Register',
            'content' => '[orabooks_register]',
            'slug' => 'orabooks-register'
        )
    );
    
    foreach ( $pages as $key => $page ) {
        $existing = get_page_by_path( $page['slug'] );
        if ( ! $existing ) {
            wp_insert_post( array(
                'post_title' => $page['title'],
                'post_name' => $page['slug'],
                'post_content' => $page['content'],
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => 1
            ) );
        }
    }
}

// Wizard page display
function orabooks_wizard_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', 'orabooks-membership' ) );
    }
    
    // Enqueue wizard styles
    wp_enqueue_style( 'orabooks-wizard', TAXORA_MEMBERSHIP_URL . 'assets/css/wizard.css', array(), TAXORA_MEMBERSHIP_VERSION );
    wp_enqueue_script( 'jquery' );
    
    $current_step = orabooks_get_wizard_step();
    
    // Add inline script for subscriber type selection highlighting
    if ( $current_step === 'general' ) {
        wp_add_inline_script( 'jquery', '
            jQuery(document).ready(function($) {
                // Highlight selected subscriber type
                function updateSubscriberTypeState() {
                    $(".orabooks-subscriber-type-option input[type=\'radio\']").each(function() {
                        var $label = $(this).closest("label");
                        if ($(this).is(":checked")) {
                            $label.addClass("selected").css({
                                "background-color": "#f0f7ef",
                                "border-color": "#43a62d",
                                "box-shadow": "0 2px 5px rgba(67, 166, 45, 0.2)"
                            });
                            $label.find("strong").css({
                                "color": "#43a62d"
                            });
                        } else {
                            $label.removeClass("selected").css({
                                "background-color": "#fff",
                                "border-color": "#ddd",
                                "box-shadow": "none"
                            });
                            $label.find("strong").css({
                                "color": "#333"
                            });
                        }
                    });
                }
                
                // Update on page load
                updateSubscriberTypeState();
                
                // Update on change
                $(".orabooks-subscriber-type-option input[type=\'radio\']").on("change", function() {
                    updateSubscriberTypeState();
                });
                
                // Add hover effect
                $(".orabooks-subscriber-type-option").hover(
                    function() {
                        if (!$(this).find("input").is(":checked")) {
                            $(this).css("border-color", "#999");
                        }
                    },
                    function() {
                        if (!$(this).find("input").is(":checked")) {
                            $(this).css("border-color", "#ddd");
                        }
                    }
                );
            });
        ' );
    }
    
    // Add inline script for payment gateway selection highlighting
    if ( $current_step === 'payments' ) {
        wp_add_inline_script( 'jquery', '
            jQuery(document).ready(function($) {
                // Highlight selected payment gateway
                function updateSelectedState() {
                    $(".orabooks-wizard__field input[type=\'radio\']").each(function() {
                        var $label = $(this).closest("label");
                        if ($(this).is(":checked")) {
                            $label.addClass("selected").css({
                                "background-color": "#f0f7ef",
                                "border-color": "#43a62d"
                            });
                            $label.find("strong").css({
                                "font-weight": "600",
                                "color": "#43a62d"
                            });
                        } else {
                            $label.removeClass("selected").css({
                                "background-color": "",
                                "border-color": ""
                            });
                            $label.find("strong").css({
                                "font-weight": "",
                                "color": ""
                            });
                        }
                    });
                }
                
                // Update on page load
                updateSelectedState();
                
                // Update on change
                $(".orabooks-wizard__field input[type=\'radio\']").on("change", function() {
                    updateSelectedState();
                });
            });
        ' );
    }
    
    // Include wizard template
    include TAXORA_MEMBERSHIP_DIR . 'wizard/wizard.php';
}

// Wizard flag is set in activation hook in database.php
