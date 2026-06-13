<?php
/**
 * Update Features Page Shortcode
 * This script updates all existing Features pages to use the correct shortcode
 * Run this once to fix existing client sites
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Update all Features pages across the network to use correct shortcode
 */
function orabooks_update_features_shortcode_network() {
    if ( ! is_multisite() ) {
        orabooks_update_features_shortcode_single_site();
        return;
    }
    
    global $wpdb;
    
    // Get all blog IDs
    $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
    
    $updated_count = 0;
    $results = array();
    
    foreach ( $blog_ids as $blog_id ) {
        switch_to_blog( $blog_id );
        
        $result = orabooks_update_features_shortcode_single_site();
        if ( $result ) {
            $updated_count++;
            $results[] = array(
                'blog_id' => $blog_id,
                'site_url' => get_site_url(),
                'updated' => true
            );
        }
        
        restore_current_blog();
    }
    
    return array(
        'total_sites' => count( $blog_ids ),
        'updated_sites' => $updated_count,
        'details' => $results
    );
}

/**
 * Update Features page on a single site
 */
function orabooks_update_features_shortcode_single_site() {
    global $wpdb;
    
    // Find the Features page by slug or title
    $features_page = get_page_by_path( 'features' );
    
    if ( ! $features_page ) {
        // Try to find by title
        $features_page = get_page_by_title( 'Features' );
    }
    
    if ( ! $features_page ) {
        return false;
    }
    
    // Check if the page contains the old shortcode
    $content = $features_page->post_content;
    
    if ( strpos( $content, '[orabooks-features]' ) !== false ) {
        // Update the shortcode from hyphen to underscore
        $new_content = str_replace( '[orabooks-features]', '[orabooks_features]', (string) $content );
        
        // Update the page
        $result = wp_update_post( array(
            'ID' => $features_page->ID,
            'post_content' => $new_content
        ) );
        
        if ( ! is_wp_error( $result ) ) {
            error_log( sprintf(
                'Updated Features page (ID: %d) shortcode from [orabooks-features] to [orabooks_features] on site: %s',
                $features_page->ID,
                get_site_url()
            ) );
            return true;
        }
    }
    
    return false;
}

/**
 * Admin page to run the update
 */
function orabooks_features_shortcode_update_page() {
    if ( ! current_user_can( 'manage_network' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', 'orabooks-membership' ) );
    }
    
    $results = null;
    
    // Handle form submission
    if ( isset( $_POST['update_shortcodes'] ) && check_admin_referer( 'orabooks_update_shortcodes' ) ) {
        $results = orabooks_update_features_shortcode_network();
    }
    
    ?>
    <div class="wrap">
        <h1>Update Features Shortcode</h1>
        
        <?php if ( $results ): ?>
            <div class="notice notice-success">
                <p><strong>Update Complete!</strong></p>
                <p>Total sites checked: <?php echo esc_html( $results['total_sites'] ); ?></p>
                <p>Sites updated: <?php echo esc_html( $results['updated_sites'] ); ?></p>
                
                <?php if ( ! empty( $results['details'] ) ): ?>
                    <h3>Updated Sites:</h3>
                    <ul>
                        <?php foreach ( $results['details'] as $detail ): ?>
                            <li>
                                Blog ID: <?php echo esc_html( $detail['blog_id'] ); ?> - 
                                <?php echo esc_html( $detail['site_url'] ); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="card" style="max-width: 800px;">
            <h2>Update Features Page Shortcode</h2>
            <p>This tool will update all Features pages across your network to use the correct shortcode format.</p>
            <p><strong>Old shortcode:</strong> <code>[orabooks-features]</code></p>
            <p><strong>New shortcode:</strong> <code>[orabooks_features]</code></p>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'orabooks_update_shortcodes' ); ?>
                <p>
                    <button type="submit" name="update_shortcodes" class="button button-primary button-large">
                        Update All Features Pages
                    </button>
                </p>
            </form>
        </div>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h3>What This Does</h3>
            <ul>
                <li>Scans all sites in your multisite network</li>
                <li>Finds the "Features" page on each site</li>
                <li>Replaces <code>[orabooks-features]</code> with <code>[orabooks_features]</code></li>
                <li>Logs all changes for your review</li>
            </ul>
            
            <p><strong>Note:</strong> This is safe to run multiple times. It will only update pages that still have the old shortcode.</p>
        </div>
    </div>
    <?php
}

// Add admin menu item
add_action( 'network_admin_menu', 'orabooks_features_shortcode_update_menu' );
// Update Features Shortcode menu disabled as not mandatory
// add_action('admin_menu', 'orabooks_features_shortcode_update_menu' );

function orabooks_features_shortcode_update_menu() {
    // Menu item disabled as requested
    return;
    
    // add_submenu_page(
    //     'orabooks-membership',
    //     'Update Features Shortcode',
    //     'Update Features Shortcode',
    //     'manage_options',
    //     'orabooks-update-features-shortcode',
    //     'orabooks_features_shortcode_update_page'
    // );
}
