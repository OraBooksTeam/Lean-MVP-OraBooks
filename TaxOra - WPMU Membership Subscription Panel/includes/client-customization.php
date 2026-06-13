<?php
/**
 * Client Customization Access
 * 
 * Allows clients to customize their own site (theme, logo, menu, colors)
 * while restricting access to other sites and admin features.
 */

if (!defined('ABSPATH')) exit;

/**
 * Allow clients to customize their own site
 */
add_filter('user_has_cap', 'orabooks_allow_client_site_customization', 10, 4);

function orabooks_allow_client_site_customization($allcaps, $caps, $args, $user) {
    // Only on client sites (not main site)
    if (get_current_blog_id() == 1) {
        return $allcaps;
    }
    
    // Check if user is the site owner
    $user_site_id = get_user_meta($user->ID, 'orabooks_site_id', true);
    $current_blog_id = get_current_blog_id();
    
    if ($user_site_id == $current_blog_id) {
        // User is on their own site, grant ALL customization capabilities
        $allcaps['edit_theme_options'] = true;  // Theme customization
        $allcaps['customize'] = true;            // Customizer access
        $allcaps['switch_themes'] = true;        // Theme switching
        $allcaps['upload_files'] = true;         // Media upload (for logo)
        $allcaps['edit_files'] = true;           // File editing
        $allcaps['edit_pages'] = true;           // Edit pages
        $allcaps['publish_pages'] = true;        // Publish pages
        $allcaps['delete_pages'] = true;         // Delete pages
        $allcaps['edit_posts'] = true;           // Edit posts
        $allcaps['publish_posts'] = true;        // Publish posts
        $allcaps['delete_posts'] = true;         // Delete posts
        $allcaps['manage_options'] = true;       // Site settings
        $allcaps['manage_categories'] = true;    // Manage categories
        $allcaps['edit_others_posts'] = true;    // Edit others' posts
        $allcaps['edit_others_pages'] = true;    // Edit others' pages
        $allcaps['administrator'] = true;        // Administrator role
    }
    
    return $allcaps;
}

/**
 * Make site owner an administrator of their own site
 */
add_action('set_current_user', 'orabooks_ensure_site_owner_is_admin');

function orabooks_ensure_site_owner_is_admin() {
    // Only on client sites
    if (get_current_blog_id() == 1) {
        return;
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        return;
    }
    
    // Check if user is the site owner
    $user_site_id = get_user_meta($user_id, 'orabooks_site_id', true);
    $current_blog_id = get_current_blog_id();
    
    if ($user_site_id == $current_blog_id) {
        $user = new WP_User($user_id);
        
        // Check if user is already an administrator on this site
        if (!in_array('administrator', $user->roles)) {
            // Add administrator role to this site
            $user->add_role('administrator');
        }
    }
}

/**
 * Disable block editor on client subsites to prevent JavaScript requirement errors.
 * Falls back to WordPress built-in classic editor — no Classic Editor plugin needed.
 */
add_filter('use_block_editor_for_post', 'orabooks_disable_block_editor', 10, 2);
add_filter('use_block_editor_for_post_type', 'orabooks_disable_block_editor', 10, 2);
function orabooks_disable_block_editor($enabled, $post_or_type) {
    if (get_current_blog_id() == 1) {
        return $enabled;
    }
    return false;
}

// Prevent block editor scripts from loading on client subsites
add_filter('wp_should_load_block_editor_scripts_and_styles', 'orabooks_prevent_block_editor_assets');
function orabooks_prevent_block_editor_assets($load) {
    if (get_current_blog_id() != 1) {
        return false;
    }
    return $load;
}

// Hide any remaining block editor error messages on client subsites
add_action('admin_head', 'orabooks_hide_block_editor_error');
function orabooks_hide_block_editor_error() {
    if (get_current_blog_id() == 1) {
        return;
    }
    global $pagenow;
    if (!in_array($pagenow, ['post.php', 'post-new.php', 'page.php', 'page-new.php'])) {
        return;
    }
    echo '<style>
        .block-editor-warning, .block-editor-writing-flow, .components-notice,
        .edit-post-welcome-guide, .edit-post-layout, .block-editor-editor-skeleton,
        #block-editor-container { display: none !important; }
        body.js #postdivrich, body.js #post-body-content { display: block !important; }
    </style>';
}

/**
 * Add customization menu to client dashboard
 */
add_action('admin_menu', 'orabooks_add_client_customization_menu', 5);

function orabooks_add_client_customization_menu() {
    // Only on client sites
    if (get_current_blog_id() == 1) {
        return;
    }
    
    // Check if user is site owner
    $user_id = get_current_user_id();
    $user_site_id = get_user_meta($user_id, 'orabooks_site_id', true);
    $current_blog_id = get_current_blog_id();
    
    if ($user_site_id == $current_blog_id || current_user_can('administrator')) {
        add_menu_page(
            'Site Customization',
            'Customize Site',
            'read',
            'orabooks-site-customization',
            'orabooks_render_customization_dashboard',
            'dashicons-admin-customizer',
            25
        );
    }
}

/**
 * Render customization dashboard
 */
function orabooks_render_customization_dashboard() {
    $user_id = get_current_user_id();
    $user_level = get_user_meta($user_id, 'orabooks_level', true);
    
    global $wpdb;
    orabooks_handle_multisite_tables();
    $level = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->orabooks_levels} WHERE id = %d",
        $user_level
    ));
    
    // Get available themes
    $themes = wp_get_themes();
    $current_theme = wp_get_theme();
    
    ?>
    <div class="wrap orabooks-customization-wrap">
        <h1>🎨 Customize Your Site</h1>
        
        <div class="orabooks-welcome-banner">
            <h2>Welcome to Your Site Dashboard!</h2>
            <p>You have full control to customize your site's appearance and settings.</p>
            <?php if ($level): ?>
                <p class="current-plan">Current Plan: <strong><?php echo esc_html($level->name); ?></strong></p>
            <?php endif; ?>
        </div>
        
        <div class="orabooks-customization-grid">
            <!-- Theme Customization -->
            <div class="customization-card">
                <div class="card-icon">🎨</div>
                <h2>Theme</h2>
                <p>Change your site's overall appearance and layout</p>
                <div class="current-info">
                    <strong>Current Theme:</strong> <?php echo esc_html($current_theme->get('Name')); ?>
                </div>
                <div class="card-actions">
                    <a href="<?php echo admin_url('themes.php'); ?>" class="button button-primary">
                        Browse Themes
                    </a>
                    <a href="<?php echo admin_url('customize.php'); ?>" class="button button-secondary">
                        Customize Current Theme
                    </a>
                </div>
            </div>
            
            <!-- Logo Upload -->
            <div class="customization-card">
                <div class="card-icon">🖼️</div>
                <h2>Logo</h2>
                <p>Upload your custom logo to brand your site</p>
                <div class="current-info">
                    <?php if (has_custom_logo()): ?>
                        <?php the_custom_logo(); ?>
                    <?php else: ?>
                        <span class="no-logo">No custom logo set</span>
                    <?php endif; ?>
                </div>
                <div class="card-actions">
                    <a href="<?php echo admin_url('customize.php?autofocus[control]=custom_logo'); ?>" class="button button-primary">
                        Upload Logo
                    </a>
                </div>
            </div>
            
            <!-- Menu Customization -->
            <div class="customization-card">
                <div class="card-icon">📋</div>
                <h2>Navigation Menu</h2>
                <p>Customize your site's navigation menu items</p>
                <div class="current-info">
                    <?php
                    $menus = wp_get_nav_menus();
                    if (!empty($menus)):
                    ?>
                        <strong>Active Menus:</strong> <?php echo count($menus); ?>
                    <?php else: ?>
                        <span class="no-menu">No custom menus created</span>
                    <?php endif; ?>
                </div>
                <div class="card-actions">
                    <a href="<?php echo admin_url('nav-menus.php'); ?>" class="button button-primary">
                        Edit Menus
                    </a>
                </div>
            </div>
            
            <!-- Colors & Fonts -->
            <div class="customization-card">
                <div class="card-icon">🎨</div>
                <h2>Colors & Fonts</h2>
                <p>Customize your site's color scheme and typography</p>
                <div class="card-actions">
                    <a href="<?php echo admin_url('customize.php?autofocus[panel]=colors'); ?>" class="button button-primary">
                        Customize Colors
                    </a>
                </div>
            </div>
            
            <!-- Pages Management -->
            <div class="customization-card">
                <div class="card-icon">📄</div>
                <h2>Pages</h2>
                <p>Create and manage your site's pages</p>
                <div class="current-info">
                    <?php
                    $pages_count = wp_count_posts('page');
                    ?>
                    <strong>Total Pages:</strong> <?php echo $pages_count->publish; ?>
                </div>
                <div class="card-actions">
                    <a href="<?php echo admin_url('edit.php?post_type=page'); ?>" class="button button-primary">
                        Manage Pages
                    </a>
                    <a href="<?php echo admin_url('post-new.php?post_type=page'); ?>" class="button button-secondary">
                        Add New Page
                    </a>
                </div>
            </div>
            
            <!-- Site Settings -->
            <div class="customization-card">
                <div class="card-icon">⚙️</div>
                <h2>Site Settings</h2>
                <p>Configure your site's general settings</p>
                <div class="card-actions">
                    <a href="<?php echo admin_url('options-general.php'); ?>" class="button button-primary">
                        General Settings
                    </a>
                </div>
            </div>
            
            <!-- Upgrade Plan -->
            <div class="customization-card upgrade-card">
                <div class="card-icon">⭐</div>
                <h2>Upgrade Your Plan</h2>
                <p>Get access to more features and capabilities</p>
                <div class="card-actions">
                    <a href="<?php echo home_url('/upgrade-plan'); ?>" class="button button-primary">
                        View Plans
                    </a>
                </div>
            </div>
            
            <!-- Help & Support -->
            <div class="customization-card">
                <div class="card-icon">❓</div>
                <h2>Help & Support</h2>
                <p>Get help with customizing your site</p>
                <div class="card-actions">
                    <a href="<?php echo network_site_url('/support'); ?>" class="button button-primary">
                        Contact Support
                    </a>
                    <a href="<?php echo network_site_url('/documentation'); ?>" class="button button-secondary">
                        View Documentation
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .orabooks-customization-wrap {
        max-width: 1400px;
        margin: 20px auto;
    }
    
    .orabooks-welcome-banner {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .orabooks-welcome-banner h2 {
        margin: 0 0 10px 0;
        font-size: 28px;
        color: white;
    }
    
    .orabooks-welcome-banner p {
        margin: 0;
        font-size: 16px;
        opacity: 0.9;
    }
    
    .orabooks-welcome-banner .current-plan {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid rgba(255, 255, 255, 0.3);
        font-size: 14px;
    }
    
    .orabooks-customization-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 25px;
    }
    
    .customization-card {
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        padding: 25px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .customization-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        border-color: #3b82f6;
    }
    
    .customization-card.upgrade-card {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border-color: #2563eb;
    }
    
    .card-icon {
        font-size: 48px;
        margin-bottom: 15px;
        text-align: center;
    }
    
    .customization-card h2 {
        margin: 0 0 10px 0;
        font-size: 20px;
        color: #333;
        text-align: center;
    }
    
    .customization-card p {
        margin: 0 0 20px 0;
        color: #666;
        font-size: 14px;
        text-align: center;
        line-height: 1.6;
    }
    
    .current-info {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        text-align: center;
        font-size: 14px;
        color: #555;
    }
    
    .current-info img {
        max-width: 150px;
        height: auto;
        display: block;
        margin: 0 auto;
    }
    
    .current-info .no-logo,
    .current-info .no-menu {
        color: #999;
        font-style: italic;
    }
    
    .card-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .card-actions .button {
        text-align: center;
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .card-actions .button-primary {
        background: #3b82f6;
        border-color: #3b82f6;
        color: white;
    }
    
    .card-actions .button-primary:hover {
        background: #2563eb;
        border-color: #2563eb;
        transform: translateY(-2px);
    }
    
    .card-actions .button-secondary {
        background: white;
        border: 2px solid #3b82f6;
        color: #3b82f6;
    }
    
    .card-actions .button-secondary:hover {
        background: #3b82f6;
        color: white;
    }
    
    @media (max-width: 768px) {
        .orabooks-customization-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
    <?php
}

/**
 * Restrict access to other sites
 */
add_action('admin_init', 'orabooks_restrict_site_access');

function orabooks_restrict_site_access() {
    // Only on client sites
    if (get_current_blog_id() == 1) {
        return;
    }
    
    // Skip for administrators
    if (current_user_can('manage_network')) {
        return;
    }
    
    $user_id = get_current_user_id();
    $user_site_id = get_user_meta($user_id, 'orabooks_site_id', true);
    $current_blog_id = get_current_blog_id();
    
    // If user is trying to access a site that's not theirs
    if ($user_site_id && $user_site_id != $current_blog_id) {
        wp_die(
            '<h1>Access Denied</h1>' .
            '<p>You do not have permission to access this site.</p>' .
            '<p><a href="' . get_site_url($user_site_id) . '">Go to your site</a></p>',
            'Access Denied',
            array('response' => 403)
        );
    }
}

/**
 * Add quick customization links to admin bar
 */
add_action('admin_bar_menu', 'orabooks_add_customization_admin_bar_links', 100);

function orabooks_add_customization_admin_bar_links($wp_admin_bar) {
    // Only on client sites
    if (get_current_blog_id() == 1) {
        return;
    }
    
    // Check if user is site owner
    $user_id = get_current_user_id();
    $user_site_id = get_user_meta($user_id, 'orabooks_site_id', true);
    $current_blog_id = get_current_blog_id();
    
    if ($user_site_id == $current_blog_id || current_user_can('administrator')) {
        $wp_admin_bar->add_node(array(
            'id' => 'orabooks-customize',
            'title' => '🎨 Customize Site',
            'href' => admin_url('admin.php?page=orabooks-site-customization'),
            'meta' => array(
                'class' => 'orabooks-customize-link'
            )
        ));
        
        $wp_admin_bar->add_node(array(
            'parent' => 'orabooks-customize',
            'id' => 'orabooks-customize-theme',
            'title' => 'Change Theme',
            'href' => admin_url('themes.php')
        ));
        
        $wp_admin_bar->add_node(array(
            'parent' => 'orabooks-customize',
            'id' => 'orabooks-customize-logo',
            'title' => 'Upload Logo',
            'href' => admin_url('customize.php?autofocus[control]=custom_logo')
        ));
        
        $wp_admin_bar->add_node(array(
            'parent' => 'orabooks-customize',
            'id' => 'orabooks-customize-menu',
            'title' => 'Edit Menu',
            'href' => admin_url('nav-menus.php')
        ));
        
        $wp_admin_bar->add_node(array(
            'parent' => 'orabooks-customize',
            'id' => 'orabooks-upgrade-plan',
            'title' => '⭐ Upgrade Plan',
            'href' => home_url('/upgrade-plan')
        ));
    }
}



