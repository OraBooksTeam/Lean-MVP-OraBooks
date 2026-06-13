<?php
/**
 * New Site Setup Handler
 * Configures new sites created via wp-signup.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Configure new site immediately after creation
 * 
 * @param int    $blog_id User ID of the new site's administrator.
 * @param int    $user_id User ID of the new site's administrator.
 * @param string $domain  The new site's domain.
 * @param string $path    The new site's path.
 * @param int    $site_id The site's ID.
 * @param array  $meta    Meta data.
 */
add_action( 'wpmu_new_blog', 'orabooks_configure_new_site', 10, 6 );

function orabooks_configure_new_site( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
    // Switch to the new blog to perform actions
    switch_to_blog( $blog_id );
    
    // 1. Create Default Pages
    $default_pages = array(
        'my-account' => array(
            'title' => 'My Account',
            'content' => '<!-- wp:shortcode -->[orabooks_my_account]<!-- /wp:shortcode -->',
            'slug' => 'my-account'
        ),
        'login' => array(
            'title' => 'Login/Logout',
            'content' => '<!-- wp:shortcode -->[orabooks_login_logout]<!-- /wp:shortcode -->',
            'slug' => 'login'
        ),
        'accounting' => array(
            'title' => 'Accounting',
            'content' => '<!-- wp:shortcode -->[orabooks_accounting]<!-- /wp:shortcode -->',
            'slug' => 'accounting'
        ),
        'inventory' => array(
            'title' => 'Inventory',
            'content' => '<!-- wp:shortcode -->[orabooks_inventory]<!-- /wp:shortcode -->',
            'slug' => 'inventory'
        )
    );
    
    foreach ($default_pages as $key => $page_info) {
        $page_data = array(
            'post_title'    => $page_info['title'],
            'post_content'  => $page_info['content'],
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_author'   => $user_id,
            'post_name'     => $page_info['slug']
        );
        
        // Check if page already exists
        $existing_page = get_page_by_path( $page_info['slug'] );
        
        if ( ! $existing_page ) {
            wp_insert_post( $page_data );
        }
    }
    
    // 3. Save the subdomain to user meta so we know they have a site
    // Extract subdomain from domain
    $subdomain = str_replace('.' . (string) DOMAIN_CURRENT_SITE, '', (string) $domain);
    update_user_meta( $user_id, 'orabooks_subdomain', $subdomain );
    update_user_meta( $user_id, 'orabooks_site_created', current_time( 'mysql' ) );
    
    // 4. Default Theme Configuration: No Sidebar
    // This helps themes that store layout settings in the database
    
    // Astra Layout
    update_option('astra-settings', array(
        'site-content-layout' => 'plain-container',
        'single-page-content-layout' => 'plain-container',
        'single-post-content-layout' => 'plain-container',
        'archive-post-content-layout' => 'plain-container',
        'site-sidebar-layout' => 'no-sidebar',
        'single-page-sidebar-layout' => 'no-sidebar',
        'single-post-sidebar-layout' => 'no-sidebar',
        'archive-post-sidebar-layout' => 'no-sidebar'
    ));

    // OceanWP Layout
    update_option('ocean_sidebar_layout', 'full-width');
    update_option('ocean_page_single_layout', 'full-width');
    
    // GeneratePress Layout
    update_option('generate_settings', array(
        'layout_setting' => 'fluid',
        'sidebar_layout_setting' => 'no-sidebar',
        'blog_layout_setting' => 'no-sidebar',
        'single_layout_setting' => 'no-sidebar'
    ));

    // 5. Ensure the Orabooks Membership plugin is active on the new site
    // (It should be network active, but if not, we can activate it here)
    // Note: If it's network active, this isn't strictly necessary, but doesn't hurt.
    
    // 5. Set default subscriber type if needed (defaults to 'individual' via get_option fallback)
    // We can explicitly set it if we want to enforce a default
    // update_option( 'orabooks_subscriber_type', 'individual' );
    
    // Restore the original blog
    restore_current_blog();
}
