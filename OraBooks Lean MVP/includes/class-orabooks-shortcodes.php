<?php
/**
 * OraBooks Shortcodes
 * 
 * Frontend-facing shortcodes for login, registration, partner onboarding, etc.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Shortcodes {
    
    private static $instance = null;
    
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
            add_shortcode('orabooks_login', [self::$instance, 'login_form']);
            add_shortcode('orabooks_register', [self::$instance, 'register_form']);
            add_shortcode('orabooks_partner_onboarding', [self::$instance, 'partner_onboarding']);
            add_shortcode('orabooks_tier_selection', [self::$instance, 'tier_selection']);
            add_shortcode('orabooks_dashboard', [self::$instance, 'dashboard']);
        }
        return self::$instance;
    }
    
    public function login_form() {
        ob_start();
        ?>
        <div class="orabooks-form-container">
            <h2><?php _e('Log In', 'orabooks'); ?></h2>
            <form id="orabooks-login-form" class="orabooks-form" method="post">
                <div class="orabooks-form-group">
                    <label for="login-email"><?php _e('Email', 'orabooks'); ?></label>
                    <input type="email" id="login-email" name="email" required placeholder="<?php esc_attr_e('Your registered email address', 'orabooks'); ?>">
                </div>
                <div class="orabooks-form-group">
                    <label for="login-password"><?php _e('Password', 'orabooks'); ?></label>
                    <input type="password" id="login-password" name="password" required placeholder="<?php esc_attr_e('Password is hidden. Keep it safe.', 'orabooks'); ?>">
                </div>
                <div class="orabooks-form-actions">
                    <button type="submit" class="orabooks-btn orabooks-btn-primary"><?php _e('Log In', 'orabooks'); ?></button>
                </div>
                <div class="orabooks-form-links">
                    <a href="#" id="orabooks-forgot-password"><?php _e('Forgot Password?', 'orabooks'); ?></a>
                    <a href="#" id="orabooks-show-register"><?php _e('Sign Up', 'orabooks'); ?></a>
                </div>
            </form>
            <div class="orabooks-oidc-section">
                <button class="orabooks-btn orabooks-btn-google" onclick="window.location.href='<?php echo esc_url(home_url('/orabooks-google-login')); ?>'">
                    <?php _e('Continue with Google', 'orabooks'); ?>
                </button>
            </div>
            <div id="orabooks-login-message" class="orabooks-message"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function register_form() {
        ob_start();
        ?>
        <div class="orabooks-form-container">
            <h2><?php _e('Create Account', 'orabooks'); ?></h2>
            <form id="orabooks-register-form" class="orabooks-form" method="post">
                <div class="orabooks-form-group">
                    <label for="reg-email"><?php _e('Email', 'orabooks'); ?></label>
                    <input type="email" id="reg-email" name="email" required>
                </div>
                <div class="orabooks-form-group">
                    <label for="reg-password"><?php _e('Password', 'orabooks'); ?></label>
                    <input type="password" id="reg-password" name="password" required 
                           pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{8,}"
                           title="<?php esc_attr_e('Min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special', 'orabooks'); ?>">
                    <small><?php _e('Min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special character', 'orabooks'); ?></small>
                </div>
                <div class="orabooks-form-group">
                    <label for="reg-confirm-password"><?php _e('Confirm Password', 'orabooks'); ?></label>
                    <input type="password" id="reg-confirm-password" name="confirm_password" required>
                </div>
                <div class="orabooks-form-group">
                    <label><?php _e('I am a:', 'orabooks'); ?></label>
                    <select id="reg-user-type" name="user_type">
                        <option value="customer"><?php _e('Customer', 'orabooks'); ?></option>
                        <option value="partner"><?php _e('Partner', 'orabooks'); ?></option>
                    </select>
                </div>
                <div class="orabooks-form-group orabooks-partner-only" style="display:none;">
                    <label for="reg-partner-type"><?php _e('Partner Type', 'orabooks'); ?></label>
                    <select id="reg-partner-type" name="partner_type">
                        <option value="individual"><?php _e('Individual', 'orabooks'); ?></option>
                        <option value="accountant"><?php _e('Accountant', 'orabooks'); ?></option>
                        <option value="agency"><?php _e('Agency', 'orabooks'); ?></option>
                        <option value="reseller"><?php _e('Reseller', 'orabooks'); ?></option>
                        <option value="strategic_partner"><?php _e('Strategic Partner', 'orabooks'); ?></option>
                    </select>
                </div>
                <div class="orabooks-form-group orabooks-partner-org" style="display:none;">
                    <label for="reg-org-name"><?php _e('Organization Name', 'orabooks'); ?></label>
                    <input type="text" id="reg-org-name" name="organization_name" 
                           placeholder="<?php esc_attr_e('Your organization name (e.g., ABC Consulting)', 'orabooks'); ?>">
                </div>
                <div class="orabooks-form-group orabooks-customer-only">
                    <label for="reg-partner-code"><?php _e('Partner Code (Optional)', 'orabooks'); ?></label>
                    <input type="text" id="reg-partner-code" name="partner_code"
                           placeholder="<?php esc_attr_e('Enter Partner Code if invited (e.g., PARTNER-A7F3E9C2)', 'orabooks'); ?>">
                </div>
                <div class="orabooks-form-group orabooks-partner-only" style="display:none;">
                    <label>
                        <input type="checkbox" id="reg-accept-terms" name="accept_terms" value="1">
                        <?php _e('I agree to Partner Terms v1.0', 'orabooks'); ?>
                    </label>
                </div>
                <div class="orabooks-form-actions">
                    <button type="submit" class="orabooks-btn orabooks-btn-primary">
                        <?php _e('Create Account', 'orabooks'); ?>
                    </button>
                </div>
            </form>
            <div id="orabooks-register-message" class="orabooks-message"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function partner_onboarding() {
        ob_start();
        ?>
        <div class="orabooks-form-container">
            <h2><?php _e('Partner Onboarding', 'orabooks'); ?></h2>
            <div id="orabooks-partner-info">
                <div class="orabooks-form-group">
                    <label><?php _e('Your Partner Code', 'orabooks'); ?></label>
                    <div class="orabooks-code-display">
                        <input type="text" id="orabooks-partner-code" readonly class="orabooks-code-input">
                        <button id="orabooks-copy-code" class="orabooks-btn orabooks-btn-secondary">
                            <?php _e('Copy Code', 'orabooks'); ?>
                        </button>
                    </div>
                </div>
                <div class="orabooks-form-group">
                    <div id="orabooks-partner-details"></div>
                </div>
                <div class="orabooks-form-group">
                    <div id="orabooks-partner-status" class="orabooks-status-badge"></div>
                </div>
                <div class="orabooks-form-actions">
                    <button id="orabooks-continue-dashboard" class="orabooks-btn orabooks-btn-primary">
                        <?php _e('Continue to Dashboard', 'orabooks'); ?>
                    </button>
                </div>
            </div>
            <div id="orabooks-onboarding-message"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function tier_selection() {
        ob_start();
        ?>
        <div class="orabooks-form-container">
            <h2><?php _e('Choose Your Plan', 'orabooks'); ?></h2>
            <form id="orabooks-tier-form" class="orabooks-form">
                <div class="orabooks-tier-options">
                    <label class="orabooks-tier-option">
                        <input type="radio" name="tier" value="free" checked>
                        <div class="orabooks-tier-card">
                            <h3><?php _e('Free', 'orabooks'); ?></h3>
                            <p><?php _e('Basic features for small businesses', 'orabooks'); ?></p>
                        </div>
                    </label>
                    <label class="orabooks-tier-option">
                        <input type="radio" name="tier" value="premium">
                        <div class="orabooks-tier-card">
                            <h3><?php _e('Premium', 'orabooks'); ?></h3>
                            <p><?php _e('Advanced features for growing businesses', 'orabooks'); ?></p>
                        </div>
                    </label>
                    <label class="orabooks-tier-option">
                        <input type="radio" name="tier" value="enterprise">
                        <div class="orabooks-tier-card">
                            <h3><?php _e('Enterprise', 'orabooks'); ?></h3>
                            <p><?php _e('Full features for large organizations', 'orabooks'); ?></p>
                        </div>
                    </label>
                </div>
                <div class="orabooks-form-group">
                    <label for="tier-subdomain"><?php _e('Choose subdomain', 'orabooks'); ?></label>
                    <div class="orabooks-subdomain-input">
                        <input type="text" id="tier-subdomain" name="subdomain" required
                               pattern="[a-z0-9][a-z0-9-]{1,61}[a-z0-9]"
                               placeholder="<?php esc_attr_e('mycompany', 'orabooks'); ?>">
                        <button type="button" id="orabooks-check-subdomain" class="orabooks-btn orabooks-btn-secondary">
                            🔍 <?php _e('Check availability', 'orabooks'); ?>
                        </button>
                    </div>
                    <small>.orabooks.app</small>
                    <div id="orabooks-subdomain-status"></div>
                </div>
                <div class="orabooks-form-actions">
                    <button type="submit" class="orabooks-btn orabooks-btn-primary">
                        <?php _e('Continue', 'orabooks'); ?>
                    </button>
                </div>
            </form>
            <div id="orabooks-tier-message"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function dashboard() {
        ob_start();
        ?>
        <div class="orabooks-dashboard">
            <h2><?php _e('Dashboard', 'orabooks'); ?></h2>
            <div id="orabooks-dashboard-content">
                <p><?php _e('Loading...', 'orabooks'); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}