<?php
if (!defined('ABSPATH')) {
    exit;
}

// Build Guide Compliance: Check permissions before displaying settings
if (class_exists('OraBooks_Permission_Matrix')) {
    $user_id = get_current_user_id();
    $role = OraBooks_Permission_Matrix::get_user_role($user_id);
    $mode = OraBooks_Permission_Matrix::get_current_mode($user_id);
    
    $permission = OraBooks_Permission_Matrix::check_permission(
        $user_id,
        $role,
        $mode,
        OraBooks_Permission_Matrix::ACTION_USER_MANAGEMENT
    );
    
    if (!$permission['allowed']) {
        wp_die('You do not have permission to access settings.');
    }
}

global $wpdb;

// Enqueue gorgeous Tailwind addon manager styles and scripts
function orabooks_enqueue_admin_addon_scripts() {
    // Enqueue Tailwind CSS from CDN
    wp_enqueue_style('tailwind-css', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css', array(), '2.2.19');
    
    // Enqueue custom gorgeous CSS
    wp_enqueue_style('orabooks-addon-manager-gorgeous', TAXORA_MEMBERSHIP_URL . 'assets/css/admin-addon-manager-gorgeous.css', array('tailwind-css'), '1.0.0');
    
    // Enqueue JavaScript
    wp_enqueue_script('orabooks-addon-manager', TAXORA_MEMBERSHIP_URL . 'assets/js/admin-addon-manager.js', array('jquery'), '1.0.0', true);
    
    // Localize script
    wp_localize_script('orabooks-addon-manager', 'orabooks_addon_manager', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('orabooks-admin-nonce')
    ));
}
add_action('admin_enqueue_scripts', 'orabooks_enqueue_admin_addon_scripts');

// Display Success Message with simple styling
if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
    echo '<div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1.5rem; color: white;">
        <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem;">Settings Updated Successfully!</h3>
        <p style="color: rgba(236, 253, 245, 1); font-size: 0.875rem;">Your changes have been saved and applied.</p>
    </div>';
}

// Get current settings
// Build Guide Compliance: Use localization system for currency
if (class_exists('OraBooks_Localization')) {
    $localization = OraBooks_Localization::get_instance();
    $currency_symbol = $localization->get_currency_symbol('BDT');
} else {
    $currency_symbol = get_option('orabooks_currency_symbol', '৳');
}
$currency_position = get_option('orabooks_currency_position', 'after');

// Build Guide Compliance: Get current mode for display
$current_mode = class_exists('OraBooks_Mode_Manager') ? OraBooks_Mode_Manager::get_current_mode() : 'business';
$mode_display = ucfirst($current_mode) . ' Mode';

// Build Guide Compliance: Log settings view for audit trail
if (class_exists('OraBooks_Audit_Logger')) {
    $logger = OraBooks_Audit_Logger::get_instance();
    $logger->log_action(array(
        'user_id' => get_current_user_id(),
        'action_type' => 'settings_view',
        'action_description' => 'Settings viewed in ' . $current_mode . ' mode',
        'mode' => $current_mode,
    ));
}

// Page ID options for removed pages have been cleaned up
$register_page_id = get_option('orabooks_register_page_id');
$login_page_id = get_option('orabooks_login_page_id');

$from_email = get_option('orabooks_from_email', get_bloginfo('admin_email'));
$from_name = get_option('orabooks_from_name', get_bloginfo('name'));

$recaptcha_site_key = orabooks_get_membership_option('orabooks_recaptcha_site_key', '');
$recaptcha_secret_key = orabooks_get_membership_option('orabooks_recaptcha_secret_key', '');

// Get features configuration
// NOTE: No default features - all features come from addon plugins
$features_config = get_option('orabooks_features_config', array());

// Get all levels for feature configuration
global $wpdb;
orabooks_handle_multisite_tables();

// FIXED: Use direct queries without prepare() when no variables are used
$levels = $wpdb->get_results("SELECT id, name, group_id FROM {$wpdb->orabooks_levels} ORDER BY name");
$groups = $wpdb->get_results("SELECT id, name FROM {$wpdb->orabooks_groups} ORDER BY name");

// Get all pages for dropdown
$pages = get_pages();
?>

<div class="wrap orabooks-admin">
    <form method="post" id="orabooks-settings-form" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="orabooks_save_settings">
        <?php wp_nonce_field('orabooks_save_settings'); ?>
        
        <div class="wrap">
    <!-- Modern Header with Gradient -->
    <div style="background: linear-gradient(135deg, #2563eb 0%, #9333ea 50%, #4f46e5 100%); border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; color: white; position: relative; overflow: hidden;">
        <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.1);"></div>
        <div style="position: relative; z-index: 10;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="font-size: 2.25rem; font-weight: 700; margin-bottom: 0.5rem;">Orabooks Settings</h1>
                    <p style="color: rgba(219,234,254,1); font-size: 1.125rem;">Configure your membership system preferences</p>
                </div>
                <div style="background: rgba(255,255,255,0.2); padding: 0.5rem 1rem; border-radius: 0.5rem;">
                    <span style="font-size: 0.875rem; font-weight: 500;"><?php echo esc_html($mode_display); ?></span>
                </div>
            </div>
        </div>
        <div style="position: absolute; top: 0; right: 0; width: 256px; height: 256px; background: rgba(255,255,255,0.05); border-radius: 50%; margin-right: -128px; margin-top: -128px;"></div>
        <div style="position: absolute; bottom: 0; left: 0; width: 192px; height: 192px; background: rgba(255,255,255,0.05); border-radius: 50%; margin-left: -96px; margin-bottom: -96px;"></div>
    </div>

    <!-- Simple Tab Navigation -->
    <div style="background: white; border-radius: 0.75rem; padding: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); margin-bottom: 2rem;">
        <nav style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
            <a href="#currency" style="background: linear-gradient(135deg, #3b82f6 0%, #4f46e5 100%); color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; text-decoration: none;">Currency</a>
            <a href="#pages" style="background: white; color: #374151; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; text-decoration: none; border: 1px solid #e5e7eb;">Pages</a>
            <a href="#emails" style="background: white; color: #374151; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; text-decoration: none; border: 1px solid #e5e7eb;">Emails</a>
            <a href="#security" style="background: white; color: #374151; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; text-decoration: none; border: 1px solid #e5e7eb;">Security</a>
            <a href="#addons" style="background: white; color: #374151; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; text-decoration: none; border: 1px solid #e5e7eb;">Addons</a>
            <a href="#advanced" style="background: white; color: #374151; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; text-decoration: none; border: 1px solid #e5e7eb;">Advanced</a>
            <a href="#payments" style="background: white; color: #374151; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; text-decoration: none; border: 1px solid #e5e7eb;">Payment Gateways</a>
        </nav>
    </div>

    <div class="tab-content">
        <!-- Currency Settings (BDT Only) -->
        <div id="currency" class="tab-pane active">
            <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); margin-bottom: 2rem;">
                <div style="margin-bottom: 1.5rem;">
                    <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Currency Settings</h2>
                    <p style="color: #6b7280; font-size: 0.875rem;">Configure your currency preferences</p>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <div style="background: #ecfdf5; border: 1px solid #d1fae5; border-radius: 0.75rem; padding: 1.5rem;">
                        <h3 style="font-size: 1.125rem; font-weight: 600; color: #064e3b; margin-bottom: 0.75rem;">Currency Settings</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr style="border-bottom: 1px solid #d1fae5;">
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #064e3b; font-size: 0.875rem;">Currency Code</th>
                                <td style="padding: 0.5rem;">
                                    <input type="text" name="currency_code" value="<?php echo esc_attr(get_option('orabooks_currency_code', 'BDT')); ?>" style="width: 100%; padding: 0.25rem; border: 1px solid #d1fae5; border-radius: 0.25rem; font-size: 0.75rem;" placeholder="e.g., BDT, USD, EUR">
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #d1fae5;">
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #064e3b; font-size: 0.875rem;">Currency Name</th>
                                <td style="padding: 0.5rem;">
                                    <input type="text" name="currency_name" value="<?php echo esc_attr(get_option('orabooks_currency_name', 'Bangladeshi Taka')); ?>" style="width: 100%; padding: 0.25rem; border: 1px solid #d1fae5; border-radius: 0.25rem; font-size: 0.75rem;" placeholder="e.g., Bangladeshi Taka, US Dollar">
                                </td>
                            </tr>
                            <tr>
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #064e3b; font-size: 0.875rem;">Currency Symbol</th>
                                <td style="padding: 0.5rem;">
                                    <input type="text" name="currency_symbol" value="<?php echo esc_attr(get_option('orabooks_currency_symbol', '৳')); ?>" style="width: 100%; padding: 0.25rem; border: 1px solid #d1fae5; border-radius: 0.25rem; font-size: 0.75rem;" placeholder="e.g., ৳, $, €">
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div style="background: #eff6ff; border: 1px solid #dbeafe; border-radius: 0.75rem; padding: 1.5rem;">
                        <h3 style="font-size: 1.125rem; font-weight: 600; color: #1e3a8a; margin-bottom: 0.75rem;">Display Settings</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr style="border-bottom: 1px solid #dbeafe;">
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #1e3a8a; font-size: 0.875rem;">Symbol Position</th>
                                <td style="padding: 0.5rem;">
                                    <select name="currency_position" style="width: 100%; padding: 0.25rem; border: 1px solid #dbeafe; border-radius: 0.25rem; font-size: 0.75rem;">
                                        <option value="before" <?php selected(get_option('orabooks_currency_position', 'before'), 'before'); ?>>Before (৳299)</option>
                                        <option value="after" <?php selected(get_option('orabooks_currency_position', 'before'), 'after'); ?>>After (299৳)</option>
                                    </select>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #dbeafe;">
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #1e3a8a; font-size: 0.875rem;">Decimal Places</th>
                                <td style="padding: 0.5rem;">
                                    <input type="number" name="currency_decimals" value="<?php echo esc_attr(get_option('orabooks_currency_decimals', '2')); ?>" min="0" max="2" style="width: 100%; padding: 0.25rem; border: 1px solid #dbeafe; border-radius: 0.25rem; font-size: 0.75rem;">
                                </td>
                            </tr>
                            <tr>
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #1e3a8a; font-size: 0.875rem;">Thousands Separator</th>
                                <td style="padding: 0.5rem;">
                                    <input type="text" name="currency_thousands_separator" value="<?php echo esc_attr(get_option('orabooks_currency_thousands_separator', ',')); ?>" maxlength="1" style="width: 100%; padding: 0.25rem; border: 1px solid #dbeafe; border-radius: 0.25rem; font-size: 0.75rem;">
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div style="background: #ecfdf5; border: 1px solid #d1fae5; border-radius: 0.75rem; padding: 1rem; margin-bottom: 2rem;">
                    <p style="color: #064e3b; font-size: 0.875rem;">
                        <strong>Note:</strong> Configure your currency settings. Changes will be saved when you update settings.
                    </p>
                </div>
            </div>
        </div>

        <!-- Page Settings -->
        <div id="pages" class="tab-pane">
            <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); margin-bottom: 2rem;">
                <div style="margin-bottom: 1.5rem;">
                    <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Page Configuration</h2>
                    <p style="color: #6b7280; font-size: 0.875rem;">Select the pages where you've placed the corresponding shortcodes</p>
                </div>

                <table style="width: 100%; border-collapse: collapse;">
                    <!-- Removed page settings for deleted pages: Pricing, Checkout, My Account, Features, Confirmation -->
                    <tr style="border-bottom: 1px solid #f3f4f6;">
                        <th style="padding: 1rem; text-align: left; font-weight: 500; color: #374151;"><label for="register_page_id" style="color: #374151; font-weight: 500;">Register Page</label></th>
                        <td style="padding: 1rem;">
                            <select id="register_page_id" name="register_page_id" style="width: 100%; max-width: 300px; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                                <option value="">-- Select Page --</option>
                                <?php foreach ($pages as $page): ?>
                                    <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($register_page_id, $page->ID); ?>>
                                        <?php echo esc_html($page->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p style="color: #6b7280; font-size: 0.75rem; margin-top: 0.5rem;">Page with [orabooks_register] shortcode</p>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding: 1rem; text-align: left; font-weight: 500; color: #374151;"><label for="login_page_id" style="color: #374151; font-weight: 500;">Login Page</label></th>
                        <td style="padding: 1rem;">
                            <select id="login_page_id" name="login_page_id" style="width: 100%; max-width: 300px; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                                <option value="">-- Select Page --</option>
                                <?php foreach ($pages as $page): ?>
                                    <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($login_page_id, $page->ID); ?>>
                                        <?php echo esc_html($page->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p style="color: #6b7280; font-size: 0.75rem; margin-top: 0.5rem;">Page with [orabooks_login] shortcode</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Email Settings -->
        <div id="emails" class="tab-pane">
            <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); margin-bottom: 2rem;">
                <div style="margin-bottom: 1.5rem;">
                    <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Email Configuration</h2>
                    <p style="color: #6b7280; font-size: 0.875rem;">Configure email settings for notifications</p>
                </div>

                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="border-bottom: 1px solid #f3f4f6;">
                        <th style="padding: 1rem; text-align: left; font-weight: 500; color: #374151;"><label for="from_email" style="color: #374151; font-weight: 500;">From Email</label></th>
                        <td style="padding: 1rem;">
                            <input type="email" id="from_email" name="from_email" value="<?php echo esc_attr($from_email); ?>" style="width: 100%; max-width: 300px; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                            <p style="color: #6b7280; font-size: 0.75rem; margin-top: 0.5rem;">Email address for sending notifications</p>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding: 1rem; text-align: left; font-weight: 500; color: #374151;"><label for="from_name" style="color: #374151; font-weight: 500;">From Name</label></th>
                        <td style="padding: 1rem;">
                            <input type="text" id="from_name" name="from_name" value="<?php echo esc_attr($from_name); ?>" style="width: 100%; max-width: 300px; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                            <p style="color: #6b7280; font-size: 0.75rem; margin-top: 0.5rem;">Sender name for emails</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Security Settings -->
        <div id="security" class="tab-pane">
            <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); margin-bottom: 2rem;">
                <div style="margin-bottom: 1.5rem;">
                    <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Security Settings</h2>
                    <p style="color: #6b7280; font-size: 0.875rem;">Configure security options</p>
                </div>

                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="border-bottom: 1px solid #f3f4f6;">
                        <th style="padding: 1rem; text-align: left; font-weight: 500; color: #374151;"><label for="recaptcha_site_key" style="color: #374151; font-weight: 500;">reCAPTCHA Site Key</label></th>
                        <td style="padding: 1rem;">
                            <input type="text" id="recaptcha_site_key" name="recaptcha_site_key" value="<?php echo esc_attr($recaptcha_site_key); ?>" style="width: 100%; max-width: 300px; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                            <p style="color: #6b7280; font-size: 0.75rem; margin-top: 0.5rem;">Google reCAPTCHA v2 site key</p>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding: 1rem; text-align: left; font-weight: 500; color: #374151;"><label for="recaptcha_secret_key" style="color: #374151; font-weight: 500;">reCAPTCHA Secret Key</label></th>
                        <td style="padding: 1rem;">
                            <input type="text" id="recaptcha_secret_key" name="recaptcha_secret_key" value="<?php echo esc_attr($recaptcha_secret_key); ?>" style="width: 100%; max-width: 300px; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                            <p style="color: #6b7280; font-size: 0.75rem; margin-top: 0.5rem;">Google reCAPTCHA v2 secret key</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Addons Manager -->
        <div id="addons" class="tab-pane">
            <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); margin-bottom: 2rem;">
                <div style="margin-bottom: 1.5rem;">
                    <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Addon Manager</h2>
                    <p style="color: #6b7280; font-size: 0.875rem;">Manage addon plugins that extend the membership system</p>
                </div>
                
                <?php
                $addons = array();
                
                if (function_exists('get_plugins')) {
                    $all_plugins = get_plugins();
                    
                    foreach ($all_plugins as $plugin_file => $plugin_data) {
                        // Check for Orabooks Addon header
                        if (isset($plugin_data['Orabooks Addon']) && strtolower($plugin_data['Orabooks Addon']) === 'true') {
                            $addon_id = sanitize_key(dirname($plugin_file));
                            $is_active = function_exists('is_plugin_active') ? is_plugin_active($plugin_file) : false;
                            
                            $addons[$addon_id] = array(
                                'id' => $addon_id,
                                'name' => $plugin_data['Name'],
                                'description' => substr($plugin_data['Description'], 0, 200),
                                'version' => $plugin_data['Version'],
                                'author' => $plugin_data['Author'] ?? '',
                                'plugin_file' => $plugin_file,
                                'enabled' => $is_active,
                                'features' => array()
                            );
                        }
                        
                        // Fallback: Force Accounting addon detection
                        if (strpos($plugin_file, 'OraBooks - WPMU Frontend Basic Accounting') !== false && !isset($addons['orabooks-wpmu-frontend-basic-accounting'])) {
                            $addon_id = 'orabooks-wpmu-frontend-basic-accounting';
                            $is_active = function_exists('is_plugin_active') ? is_plugin_active($plugin_file) : false;
                            
                            $addons[$addon_id] = array(
                                'id' => $addon_id,
                                'name' => $plugin_data['Name'],
                                'description' => substr($plugin_data['Description'], 0, 200),
                                'version' => $plugin_data['Version'],
                                'author' => $plugin_data['Author'] ?? '',
                                'plugin_file' => $plugin_file,
                                'enabled' => $is_active,
                                'features' => array(
                                    'accounting' => array(
                                        'name' => 'Accounting System',
                                        'description' => 'Complete double-entry accounting system for founder users.',
                                        'icon' => '📊',
                                        'category' => 'business'
                                    )
                                )
                            );
                        }
                    }
                }
                ?>

                <?php if (empty($addons)): ?>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 2rem; text-align: center;">
                        <div style="background: #f1f5f9; width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                            <svg style="width: 32px; height: 32px; color: #64748b;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                        <h3 style="font-size: 1.125rem; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">No Addons Found</h3>
                        <p style="color: #64748b; font-size: 0.875rem;">No Orabooks addons are currently installed or active.</p>
                    </div>
                <?php else: ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem;">
                        <?php foreach ($addons as $addon_id => $addon): ?>
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.5rem; border-left: 4px solid <?php echo $addon['enabled'] ? '#10b981' : '#6b7280'; ?>;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                    <div>
                                        <h4 style="font-size: 1rem; font-weight: 600; color: #1e293b; margin-bottom: 0.25rem;"><?php echo esc_html($addon['name']); ?></h4>
                                        <p style="color: #64748b; font-size: 0.75rem;">Version <?php echo esc_html($addon['version']); ?></p>
                                    </div>
                                    <div style="background: <?php echo $addon['enabled'] ? '#d1fae5' : '#f1f5f9'; ?> color: <?php echo $addon['enabled'] ? '#065f46' : '#64748b'; ?> padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.625rem; font-weight: 500;">
                                        <?php echo $addon['enabled'] ? 'Active' : 'Inactive'; ?>
                                    </div>
                                </div>
                                
                                <p style="color: #475569; font-size: 0.8125rem; margin-bottom: 1rem; line-height: 1.5;"><?php echo esc_html($addon['description']); ?></p>
                                
                                <?php if (!empty($addon['features'])): ?>
                                    <div style="margin-bottom: 1rem;">
                                        <h5 style="font-size: 0.75rem; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Features:</h5>
                                        <ul style="list-style: none; padding: 0; margin: 0;">
                                            <?php foreach ($addon['features'] as $feature): ?>
                                                <li style="color: #64748b; font-size: 0.6875rem; margin-bottom: 0.25rem; display: flex; align-items: center;">
                                                    <svg style="width: 12px; height: 12px; color: #10b981; margin-right: 0.375rem;" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    <?php echo esc_html($feature['name']); ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                                    <div style="font-size: 0.6875rem; color: #64748b;">
                                        By <?php echo esc_html($addon['author'] ?? 'Unknown'); ?>
                                    </div>
                                    <div>
                                        <?php if ($addon['enabled']): ?>
                                            <span style="background: #10b981; color: white; padding: 0.375rem 0.75rem; border-radius: 0.375rem; font-size: 0.75rem;">Active</span>
                                        <?php else: ?>
                                            <span style="background: #6b7280; color: white; padding: 0.375rem 0.75rem; border-radius: 0.375rem; font-size: 0.75rem;">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Advanced Settings -->
        <div id="advanced" class="tab-pane">
            <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); margin-bottom: 2rem;">
                <div style="margin-bottom: 1.5rem;">
                    <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Advanced Settings</h2>
                    <p style="color: #6b7280; font-size: 0.875rem;">Advanced configuration options</p>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="border-bottom: 1px solid #f3f4f6;">
                        <th style="padding: 1rem; text-align: left; font-weight: 500; color: #374151;">Debug Mode</th>
                        <td style="padding: 1rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" id="debug_mode" name="debug_mode" value="1" disabled style="width: 1rem; height: 1rem;">
                                <span style="color: #6b7280; font-size: 0.875rem;">Enable debug logging (Coming Soon)</span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding: 1rem; text-align: left; font-weight: 500; color: #374151;">Data Cleanup</th>
                        <td style="padding: 1rem;">
                            <button type="button" style="background: #9ca3af; color: white; padding: 0.5rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; border: none; cursor: not-allowed;" disabled>Cleanup Old Data (Coming Soon)</button>
                            <p style="color: #6b7280; font-size: 0.75rem; margin-top: 0.5rem;">Remove temporary and old data (orders older than 1 year)</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Payment Gateways -->
        <div id="payments" class="tab-pane">
            <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); margin-bottom: 2rem;">
                <div style="margin-bottom: 1.5rem;">
                    <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Payment Gateway Settings</h2>
                    <p style="color: #6b7280; font-size: 0.875rem;">Configure payment gateways to accept payments</p>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <!-- ShurjoPay -->
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 0.75rem; padding: 1.5rem;">
                        <h3 style="font-size: 1.125rem; font-weight: 600; color: #166534; margin-bottom: 0.75rem;">ShurjoPay</h3>
                        <p style="color: #15803d; font-size: 0.875rem; margin-bottom: 1rem;">Bangladeshi payment gateway</p>
                        
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1rem;">
                            <tr style="border-bottom: 1px solid #dcfce7;">
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #166534; font-size: 0.875rem;">Status</th>
                                <td style="padding: 0.5rem;">
                                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                                        <input type="checkbox" name="shurjopay_enabled" value="1" <?php checked(orabooks_get_membership_option('orabooks_shurjopay_enabled', 1)); ?> style="margin: 0;">
                                        <span style="font-size: 0.875rem;">Enable ShurjoPay</span>
                                    </label>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #dcfce7;">
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #166534; font-size: 0.875rem;">API URL</th>
                                <td style="padding: 0.5rem;">
                                    <input type="text" name="shurjopay_api_url" value="<?php echo esc_attr(orabooks_get_membership_option('orabooks_shurjopay_api_url', 'https://engine.shurjopayment.com')); ?>" style="width: 100%; padding: 0.25rem; border: 1px solid #bbf7d0; border-radius: 0.25rem; font-size: 0.75rem;">
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #dcfce7;">
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #166534; font-size: 0.875rem;">Username</th>
                                <td style="padding: 0.5rem;">
                                    <input type="text" name="shurjopay_username" value="<?php echo esc_attr(orabooks_get_membership_option('orabooks_shurjopay_username', 'ExtremenestCorporation')); ?>" style="width: 100%; padding: 0.25rem; border: 1px solid #bbf7d0; border-radius: 0.25rem; font-size: 0.75rem;">
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #dcfce7;">
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #166534; font-size: 0.875rem;">Password</th>
                                <td style="padding: 0.5rem;">
                                    <input type="password" name="shurjopay_password" value="" placeholder="Enter new password to update" style="width: 100%; padding: 0.25rem; border: 1px solid #bbf7d0; border-radius: 0.25rem; font-size: 0.75rem;">
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #dcfce7;">
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #166534; font-size: 0.875rem;">Order Prefix</th>
                                <td style="padding: 0.5rem;">
                                    <input type="text" name="shurjopay_prefix" value="<?php echo esc_attr(orabooks_get_membership_option('orabooks_shurjopay_prefix', 'ETC')); ?>" style="width: 100%; padding: 0.25rem; border: 1px solid #bbf7d0; border-radius: 0.25rem; font-size: 0.75rem;">
                                </td>
                            </tr>
                            <tr>
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #166534; font-size: 0.875rem;">Test Mode</th>
                                <td style="padding: 0.5rem;">
                                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                                        <input type="checkbox" name="shurjopay_test_mode" value="1" <?php checked(orabooks_get_membership_option('orabooks_shurjopay_test_mode', 0)); ?> style="margin: 0;">
                                        <span style="font-size: 0.875rem;">Enable Test Mode</span>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Bank Transfer -->
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 0.75rem; padding: 1.5rem;">
                        <h3 style="font-size: 1.125rem; font-weight: 600; color: #166534; margin-bottom: 0.75rem;">Bank Transfer</h3>
                        <p style="color: #15803d; font-size: 0.875rem; margin-bottom: 1rem;">Direct bank payment method</p>
                        
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1rem;">
                            <tr style="border-bottom: 1px solid #dcfce7;">
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #166534; font-size: 0.875rem;">Status</th>
                                <td style="padding: 0.5rem;">
                                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                                        <input type="checkbox" name="bank_transfer_enabled" value="1" <?php checked(orabooks_get_membership_option('orabooks_bank_transfer_enabled', 1)); ?> style="margin: 0;">
                                        <span style="font-size: 0.875rem;">Enable Bank Transfer</span>
                                    </label>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #dcfce7;">
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #166534; font-size: 0.875rem;">Bank Name</th>
                                <td style="padding: 0.5rem;">
                                    <input type="text" name="bank_transfer_bank_name" value="<?php echo esc_attr(orabooks_get_membership_option('orabooks_bank_transfer_bank_name', '')); ?>" style="width: 100%; padding: 0.25rem; border: 1px solid #bbf7d0; border-radius: 0.25rem; font-size: 0.75rem;">
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #dcfce7;">
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #166534; font-size: 0.875rem;">Account Name</th>
                                <td style="padding: 0.5rem;">
                                    <input type="text" name="bank_transfer_account_name" value="<?php echo esc_attr(orabooks_get_membership_option('orabooks_bank_transfer_account_name', '')); ?>" style="width: 100%; padding: 0.25rem; border: 1px solid #bbf7d0; border-radius: 0.25rem; font-size: 0.75rem;">
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #dcfce7;">
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #166534; font-size: 0.875rem;">Account Number</th>
                                <td style="padding: 0.5rem;">
                                    <input type="text" name="bank_transfer_account_number" value="<?php echo esc_attr(orabooks_get_membership_option('orabooks_bank_transfer_account_number', '')); ?>" style="width: 100%; padding: 0.25rem; border: 1px solid #bbf7d0; border-radius: 0.25rem; font-size: 0.75rem;">
                                </td>
                            </tr>
                            <tr>
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #166534; font-size: 0.875rem;">Instructions</th>
                                <td style="padding: 0.5rem;">
                                    <textarea name="bank_transfer_instructions" rows="3" style="width: 100%; padding: 0.25rem; border: 1px solid #bbf7d0; border-radius: 0.25rem; font-size: 0.75rem;"><?php echo esc_textarea(orabooks_get_membership_option('orabooks_bank_transfer_instructions', 'Please transfer the amount to the bank account above and send us the receipt.')); ?></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- SSL Commerz -->
                    <div style="background: #fef3c7; border: 1px solid #fcd34d; border-radius: 0.75rem; padding: 1.5rem;">
                        <h3 style="font-size: 1.125rem; font-weight: 600; color: #92400e; margin-bottom: 0.75rem;">SSL Commerz</h3>
                        <p style="color: #b45309; font-size: 0.875rem; margin-bottom: 1rem;">Bangladeshi payment gateway</p>
                        
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1rem;">
                            <tr style="border-bottom: 1px solid #fde68a;">
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #92400e; font-size: 0.875rem;">Status</th>
                                <td style="padding: 0.5rem;">
                                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                                        <input type="checkbox" name="sslcommerz_enabled" value="1" <?php checked(orabooks_get_membership_option('orabooks_sslcommerz_enabled', 0)); ?> style="margin: 0;">
                                        <span style="font-size: 0.875rem;">Enable SSL Commerz</span>
                                    </label>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #fde68a;">
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #92400e; font-size: 0.875rem;">Store ID</th>
                                <td style="padding: 0.5rem;">
                                    <input type="text" name="sslcommerz_store_id" value="<?php echo esc_attr(orabooks_get_membership_option('orabooks_sslcommerz_store_id', '')); ?>" style="width: 100%; padding: 0.25rem; border: 1px solid #fcd34d; border-radius: 0.25rem; font-size: 0.75rem;">
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #fde68a;">
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #92400e; font-size: 0.875rem;">Store Password</th>
                                <td style="padding: 0.5rem;">
                                    <input type="password" name="sslcommerz_store_password" value="" placeholder="Enter new password to update" style="width: 100%; padding: 0.25rem; border: 1px solid #fcd34d; border-radius: 0.25rem; font-size: 0.75rem;">
                                </td>
                            </tr>
                            <tr>
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #92400e; font-size: 0.875rem;">Test Mode</th>
                                <td style="padding: 0.5rem;">
                                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                                        <input type="checkbox" name="sslcommerz_test_mode" value="1" <?php checked(orabooks_get_membership_option('orabooks_sslcommerz_test_mode', 1)); ?> style="margin: 0;">
                                        <span style="font-size: 0.875rem;">Enable Test Mode</span>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- PayPal -->
                    <div style="background: #fef3c7; border: 1px solid #fcd34d; border-radius: 0.75rem; padding: 1.5rem;">
                        <h3 style="font-size: 1.125rem; font-weight: 600; color: #92400e; margin-bottom: 0.75rem;">PayPal</h3>
                        <p style="color: #b45309; font-size: 0.875rem; margin-bottom: 1rem;">International payment gateway</p>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr style="border-bottom: 1px solid #fde68a;">
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #92400e; font-size: 0.875rem;">Status</th>
                                <td style="padding: 0.5rem;">
                                    <span style="background: #9ca3af; color: white; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500;">Inactive</span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Stripe -->
                    <div style="background: #fef3c7; border: 1px solid #fcd34d; border-radius: 0.75rem; padding: 1.5rem;">
                        <h3 style="font-size: 1.125rem; font-weight: 600; color: #92400e; margin-bottom: 0.75rem;">Stripe</h3>
                        <p style="color: #b45309; font-size: 0.875rem; margin-bottom: 1rem;">International payment gateway</p>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr style="border-bottom: 1px solid #fde68a;">
                                <th style="padding: 0.5rem; text-align: left; font-weight: 500; color: #92400e; font-size: 0.875rem;">Status</th>
                                <td style="padding: 0.5rem;">
                                    <span style="background: #9ca3af; color: white; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500;">Inactive</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #374151; margin-bottom: 0.75rem;">Currency Information</h3>
                    <p style="color: #6b7280; font-size: 0.875rem;"><strong>Default Currency:</strong> Bangladeshi Taka (BDT) - Only BDT is supported for all payments</p>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div style="margin-top: 2rem;">
            <button type="submit" style="background: linear-gradient(135deg, #3b82f6 0%, #4f46e5 100%); color: white; padding: 0.75rem 2rem; border-radius: 0.5rem; font-size: 1rem; font-weight: 500; border: none; cursor: pointer;">Save Settings</button>
        </div>
    </div>
    </form>
</div>