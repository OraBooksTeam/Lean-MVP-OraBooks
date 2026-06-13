<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Frontend_Inventory_Dashboard {

    public function __construct() {
        // We use template_include to hijack the page template for full isolation
        add_filter( 'template_include', [ $this, 'load_dashboard_template' ] );
        
        // Register the shortcode
        add_shortcode( 'orabooks_inventory', [ $this, 'render_dashboard_shortcode' ] );
    }

    public function render_dashboard_shortcode() {
        // Logged in but no access
        if ( ! is_user_logged_in() ) {
            return '<div class="notice notice-warning"><p>Please <a href="' . wp_login_url( get_permalink() ) . '">log in</a> to access your inventory.</p></div>';
        }

        if ( ! orabooks_can_access_inventory() ) {
            return '<div class="notice notice-error">
                <h3>Access Denied</h3>
                <p>Your current membership plan does not include access to the Inventory System.</p>
                <p>Please <a href="' . home_url( '/pricing' ) . '">upgrade your plan</a> to access this feature.</p>
            </div>';
        }

        // The template hijacking handles the actual rendering via template_include
        // This is just a placeholder to ensure the shortcode exists
        return '';
    }

    public function load_dashboard_template( $template ) {
        if ( is_page() ) {
            global $post;
            
            // Check for shortcode OR specific slug 'inventory-dashboard'
            if ( has_shortcode( $post->post_content, 'orabooks_inventory' ) || $post->post_name === 'inventory-dashboard' ) {
                
                // CRITICAL: Check Access first
                if ( ! orabooks_can_access_inventory() ) {
                     return $template; // Fallback to theme, which shows shortcode's message
                }
                
                // Return our custom full-page template
                return FRONTEND_INVENTORY_PATH . 'templates/dashboard.php';
            }
        }
        return $template;
    }
}
