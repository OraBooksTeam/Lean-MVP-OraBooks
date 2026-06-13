<?php
/**
 * Quick Script to Create Inventory Page
 * 
 * INSTRUCTIONS:
 * 1. Upload this file to your WordPress root directory
 * 2. Visit: https://nasir008.fundsme.xyz/create-inventory-page.php
 * 3. The page will be created automatically
 * 4. Delete this file after use for security
 */

// Load WordPress
require_once('wp-load.php');

// Check if user is logged in and has admin rights
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('You must be logged in as an administrator to run this script.');
}

// Check if page already exists
$existing_page = get_page_by_path('inventory');

if ($existing_page) {
    echo '<h1>Inventory Page Already Exists!</h1>';
    echo '<p>Page ID: ' . $existing_page->ID . '</p>';
    echo '<p>URL: <a href="' . get_permalink($existing_page->ID) . '">' . get_permalink($existing_page->ID) . '</a></p>';
    echo '<p><a href="' . admin_url('post.php?post=' . $existing_page->ID . '&action=edit') . '">Edit Page</a></p>';
} else {
    // Create the page
    $page_data = array(
        'post_title'    => 'Inventory',
        'post_content'  => '<!-- wp:shortcode -->[orabooks_inventory]<!-- /wp:shortcode -->',
        'post_status'   => 'publish',
        'post_type'     => 'page',
        'post_author'   => get_current_user_id(),
        'post_name'     => 'inventory'
    );
    
    $page_id = wp_insert_post($page_data);
    
    if ($page_id && !is_wp_error($page_id)) {
        echo '<h1>✅ Success! Inventory Page Created</h1>';
        echo '<p>Page ID: ' . $page_id . '</p>';
        echo '<p>URL: <a href="' . get_permalink($page_id) . '" target="_blank">' . get_permalink($page_id) . '</a></p>';
        echo '<p><a href="' . admin_url('post.php?post=' . $page_id . '&action=edit') . '">Edit Page</a></p>';
        echo '<hr>';
        echo '<p><strong>IMPORTANT:</strong> Delete this file (create-inventory-page.php) from your server for security!</p>';
    } else {
        echo '<h1>❌ Error Creating Page</h1>';
        echo '<p>Error: ' . ($page_id instanceof WP_Error ? $page_id->get_error_message() : 'Unknown error') . '</p>';
    }
}

echo '<hr>';
echo '<h2>Alternative: Create Manually</h2>';
echo '<ol>';
echo '<li>Go to <a href="' . admin_url('post-new.php?post_type=page') . '">Pages → Add New</a></li>';
echo '<li>Title: <strong>Inventory</strong></li>';
echo '<li>Slug: <strong>inventory</strong></li>';
echo '<li>Content: <code>[orabooks_inventory]</code></li>';
echo '<li>Click Publish</li>';
echo '</ol>';
?>
