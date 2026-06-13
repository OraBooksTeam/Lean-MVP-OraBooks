<?php
/**
 * Settings Admin Template
 */

// Get current settings
$settings = $this->get_all_settings();
?>

<div class="taxora-admin-settings">
    <div class="taxora-admin-header">
        <h1>Settings</h1>
        <div class="taxora-admin-actions">
            <a href="<?php echo admin_url('admin.php?page=taxora-plans'); ?>" class="button button-secondary">Manage Plans</a>
            <button type="submit" name="save_settings" class="button button-primary">Save Settings</button>
        </div>
    </div>
    
    <form method="post" action="">
        <div class="taxora-settings-sections">
            <!-- General Settings -->
            <div class="taxora-settings-section">
                <h2>General Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="site_title">Site Title</label>
                        </th>
                        <td>
                            <input type="text" name="site_title" id="site_title" value="<?php echo esc_attr($settings['site_title']); ?>" class="regular-text">
                            <p class="description">The name of your membership site</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="tagline">Tagline</label>
                        </th>
                        <td>
                            <input type="text" name="tagline" id="tagline" value="<?php echo esc_attr($settings['tagline']); ?>" class="regular-text">
                            <p class="description">A short description of your site</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="admin_email">Admin Email</label>
                        </th>
                        <td>
                            <input type="email" name="admin_email" id="admin_email" value="<?php echo esc_attr($settings['admin_email']); ?>" class="regular-text">
                            <p class="description">Administrative contact email</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="enable_registration">Enable Registration</label>
                        </th>
                        <td>
                            <select name="enable_registration" id="enable_registration">
                                <option value="1" <?php selected($settings['enable_registration'], '1'); ?>>Yes</option>
                                <option value="0" <?php selected($settings['enable_registration'], '0'); ?>>No</option>
                            </select>
                            <p class="description">Allow new users to register</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="default_plan">Default Plan</label>
                        </th>
                        <td>
                            <select name="default_plan" id="default_plan">
                                <?php
                                $plans = $this->get_all_plans();
                                foreach ($plans as $plan) {
                                    $selected = $settings['default_plan'] == $plan->id ? 'selected' : '';
                                    echo '<option value="' . $plan->id . '" ' . $selected . '>' . esc_html($plan->name) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description">Default plan for new registrations</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Payment Settings -->
            <div class="taxora-settings-section">
                <h2>Payment Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="currency">Currency</label>
                        </th>
                        <td>
                            <select name="currency" id="currency">
                                <option value="USD" <?php selected($settings['currency'], 'USD'); ?>>USD ($)</option>
                                <option value="EUR" <?php selected($settings['currency'], 'EUR'); ?>>EUR (€)</option>
                                <option value="GBP" <?php selected($settings['currency'], 'GBP'); ?>>GBP (£)</option>
                            </select>
                            <p class="description">Payment currency</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="payment_gateway">Payment Gateway</label>
                        </th>
                        <td>
                            <select name="payment_gateway" id="payment_gateway">
                                <option value="stripe" <?php selected($settings['payment_gateway'], 'stripe'); ?>>Stripe</option>
                                <option value="paypal" <?php selected($settings['payment_gateway'], 'paypal'); ?>>PayPal</option>
                                <option value="manual" <?php selected($settings['payment_gateway'], 'manual'); ?>>Manual</option>
                            </select>
                            <p class="description">Payment processing service</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="trial_period">Trial Period (days)</label>
                        </th>
                        <td>
                            <input type="number" name="trial_period" id="trial_period" value="<?php echo esc_attr($settings['trial_period']); ?>" min="0" max="365">
                            <p class="description">Free trial length in days</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Email Settings -->
            <div class="taxora-settings-section">
                <h2>Email Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="email_from_name">From Name</label>
                        </th>
                        <td>
                            <input type="text" name="email_from_name" id="email_from_name" value="<?php echo esc_attr($settings['email_from_name']); ?>" class="regular-text">
                            <p class="description">Email sender name</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="email_from_email">From Email</label>
                        </th>
                        <td>
                            <input type="email" name="email_from_email" id="email_from_email" value="<?php echo esc_attr($settings['email_from_email']); ?>" class="regular-text">
                            <p class="description">Email sender address</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="welcome_email_template">Welcome Email</label>
                        </th>
                        <td>
                            <textarea name="welcome_email_template" id="welcome_email_template" rows="10" class="large-text"><?php echo esc_textarea($settings['welcome_email_template']); ?></textarea>
                            <p class="description">Email template for new registrations</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Security Settings -->
            <div class="taxora-settings-section">
                <h2>Security Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="force_ssl">Force SSL</label>
                        </th>
                        <td>
                            <select name="force_ssl" id="force_ssl">
                                <option value="1" <?php selected($settings['force_ssl'], '1'); ?>>Yes</option>
                                <option value="0" <?php selected($settings['force_ssl'], '0'); ?>>No</option>
                            </select>
                            <p class="description">Force HTTPS for all pages</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="session_timeout">Session Timeout (minutes)</label>
                        </th>
                        <td>
                            <input type="number" name="session_timeout" id="session_timeout" value="<?php echo esc_attr($settings['session_timeout']); ?>" min="5" max="1440">
                            <p class="description">User session duration</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php wp_nonce_field('taxora_settings', 'taxora_settings_nonce'); ?>
        <div class="taxora-settings-footer">
            <p><strong>Note:</strong> Save settings to apply changes. Some settings may require clearing your cache.</p>
        </div>
    </form>
</div>

<?php
// Helper methods
class Taxora_Settings_Helper {
    
    public function get_all_settings() {
        // Default settings
        $defaults = [
            'site_title' => 'TaxOra Membership',
            'tagline' => 'Professional membership management',
            'admin_email' => get_option('admin_email'),
            'enable_registration' => '1',
            'default_plan' => '1',
            'currency' => 'USD',
            'payment_gateway' => 'stripe',
            'trial_period' => '7',
            'email_from_name' => get_bloginfo('name'),
            'email_from_email' => get_option('admin_email'),
            'welcome_email_template' => $this->get_default_welcome_template(),
            'force_ssl' => '0',
            'session_timeout' => '30'
        ];
        
        // Get saved settings
        $saved = get_option('taxora_settings', []);
        
        // Merge defaults with saved settings
        return wp_parse_args($saved, $defaults);
    }
    
    private function get_default_welcome_template() {
        return 'Welcome to {site_title}!

Your membership has been successfully created.

Plan: {plan_name}
Price: {price}/billing_cycle}
Features: {features}

Login to your dashboard: {login_url}

Thank you for choosing {site_title}!';
    }
}

$settings_helper = new Taxora_Settings_Helper();
