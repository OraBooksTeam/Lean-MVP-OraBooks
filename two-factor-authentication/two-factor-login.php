<?php
/*
Plugin Name: Two Factor Authentication
Plugin URI: https://www.simbahosting.co.uk/s3/product/two-factor-authentication/
Description: Secure your WordPress login forms with two factor authentication - including WooCommerce login forms
Author: David Anderson, original plugin by Oskar Hane and enhanced by Dee Nutbourne
Author URI: https://www.simbahosting.co.uk
Version: 1.16.0
Text Domain: two-factor-authentication
Domain Path: /languages
License: GPLv2 or later
*/

register_activation_hook(__FILE__, 'simba_two_factor_authentication_activation');

if (!function_exists('simba_two_factor_authentication_activation')) {
	function simba_two_factor_authentication_activation() {
		if (!empty($GLOBALS['simba_two_factor_authentication'])) {
			$is_2fa_plugin_active = false;
			$installed_plugins_slugs = array_keys(get_plugins());
			foreach ($installed_plugins_slugs as $installed_plugin_slug) {
				if (is_plugin_active($installed_plugin_slug)) {
					$temp_split_plugin_slug = explode('/', $installed_plugin_slug);
					if (isset($temp_split_plugin_slug[1]) && 'two-factor-login.php' == $temp_split_plugin_slug[1]) {
						$is_2fa_plugin_active = true;
						break;
					}
				}
			}

			// We should prevent activation if and only if either the 2FA Premium or 2FA Free plugin is active.
			// We should not prevent activation if either the AIOS plugin is active.
			if ($is_2fa_plugin_active) {
				if (file_exists(__DIR__.'/simba-tfa/premium/loader.php')) {
					wp_die(esc_html__('To activate Two Factor Authentication Premium, first de-activate the free version (only one can be active at once).', 'two-factor-authentication'));
				} else { // If the 2FA Premium plugin is active and tries to activate the 2FA Free Plugin, it throws a fatal error and stops activating the free version.
					wp_die(esc_html__("You can't activate Two Factor Authentication (Free) because Two Factor Authentication Premium is active (only one can be active at once).", 'two-factor-authentication'));
				}
			}
		}
	}
}

if (!defined('SIMBA_TFA_TEXT_DOMAIN')) define('SIMBA_TFA_TEXT_DOMAIN', 'two-factor-authentication');
if (!class_exists('Simba_Two_Factor_Authentication_1')) require dirname(__FILE__).'/simba-tfa/simba-tfa.php';

if (!class_exists('Simba_Two_Factor_Authentication_Plugin')):
/**
 * This parent-child relationship enables the two to be split without affecting backwards compatibility for developers making direct calls
 * 
 * This class is for the plugin encapsulation.
 */
class Simba_Two_Factor_Authentication_Plugin extends Simba_Two_Factor_Authentication_1 {
	
	public $version = '1.16.0';
	
	const PHP_REQUIRED = '5.6';
	
	/**
	 * Constructor, run upon plugin initiation
	 *
	 * @uses __FILE__
	 */
	public function __construct() {
		
		add_action('plugins_loaded', array($this, 'plugins_loaded_load_textdomain'));
		
		add_action('init', array($this, 'init_child'));
		
		if (version_compare(PHP_VERSION, self::PHP_REQUIRED, '<' )) {
			add_action('all_admin_notices', array($this, 'admin_notice_insufficient_php'));
			$abort = true;
		}
		
		if (!function_exists('mcrypt_get_iv_size') && !function_exists('openssl_cipher_iv_length')) {
			add_action('all_admin_notices', array($this, 'admin_notice_missing_mcrypt_and_openssl'));
			$abort = true;
		}

		$encryption_enabled = $this->get_option('tfa_encrypt_secrets');
		if ($encryption_enabled && (!defined('SIMBA_TFA_DB_ENCRYPTION_KEY') || '' === SIMBA_TFA_DB_ENCRYPTION_KEY)) {
			add_action('all_admin_notices', array($this, 'admin_notice_missing_db_encryption_key'));
		}
		
		if (!empty($abort)) return;
		
		// Menu entries
		add_action('admin_menu', array($this, 'menu_entry_for_admin'));
		add_action('admin_menu', array($this, 'menu_entry_for_user'));
		add_action('network_admin_menu', array($this, 'menu_entry_for_user'));
		
		// Add settings link in plugin list
		$plugin = plugin_basename(__FILE__); 
		add_filter("plugin_action_links_$plugin", array($this, 'add_plugin_settings_link'));
		add_filter("network_admin_plugin_action_links_$plugin", array($this, 'add_plugin_settings_link'));
		
		$this->set_user_settings_page_slug('two-factor-auth-user');

		$this->set_plugin_translate_url('https://translate.wordpress.org/projects/wp-plugins/two-factor-authentication/');
		
		if (is_multisite() && function_exists('switch_to_blog')) {
			$main_site_id = function_exists('get_main_site_id') ? get_main_site_id() : 1;
			switch_to_blog($main_site_id);
		}
		$this->set_site_wide_administration_url(admin_url('options-general.php?page=two-factor-auth'));
		if (is_multisite() && function_exists('restore_current_blog')) restore_current_blog();
		
		$this->set_premium_version_url('https://www.simbahosting.co.uk/s3/product/two-factor-authentication/');
		$this->set_faq_url('https://wordpress.org/plugins/two-factor-authentication/#faq');
		parent::__construct();
		
	}
	
	/**
	 * Runs upon the WP filters plugin_action_links_(plugin) and network_plugin_action_links_(plugin)
	 *
	 * @param Array $links
	 *
	 * @return Array
	 */
	public function add_plugin_settings_link($links) {
		if (is_multisite()) {
				$main_site_id = function_exists('get_main_site_id') ? get_main_site_id() : 1;
				switch_to_blog($main_site_id);
				$link = $this->get_settings_link();
				restore_current_blog();
				array_unshift($links, $link);
		} else {
			$link = $this->get_settings_link();
			array_unshift($links, $link);
		}
		
		$link2 = '<a href="admin.php?page=two-factor-auth-user">'.__('User settings', 'two-factor-authentication').'</a>';
		array_unshift($links, $link2);
		
		return $links;
	}

	/**
	 * Get 2FA settings anchor tag link.
	 *
	 * @return string 2FA settings anchor tag link.
	 */
	private function get_settings_link() {
		return '<a href="'.admin_url('options-general.php').'?page=two-factor-auth">'.__('Plugin settings', 'two-factor-authentication').'</a>';
	}
	
	/**
	 * Runs upon the WP actions admin_menu and network_admin_menu
	 */
	public function menu_entry_for_user() {
		
		global $current_user;
		if ($this->is_activated_for_user($current_user->ID)) {
			add_menu_page(__('Two Factor Authentication', 'two-factor-authentication'), __('Two Factor Auth', 'two-factor-authentication'), 'read', 'two-factor-auth-user', array($this, 'show_dashboard_user_settings_page'), $this->includes_url().'/tfa_admin_icon_16x16.png', 72);
		}
	}
	
	/**
	 * Runs upon the WP action admin_menu
	 */
	public function menu_entry_for_admin() {
		
		$skip_adding_options_menu_entry = (is_multisite() && (!is_super_admin() || !is_main_site()));
		
		$skip_adding_options_menu_entry = apply_filters('simba_tfa_skip_adding_options_menu_entry', $skip_adding_options_menu_entry);
		
		if ($skip_adding_options_menu_entry) return;
		
		add_options_page(
			__('Two Factor Authentication', 'two-factor-authentication'),
			__('Two Factor Authentication', 'two-factor-authentication'),
			$this->get_management_capability(),
			'two-factor-auth',
			array($this, 'show_admin_settings_page')
		);
	}
	
	/**
	 * Include the admin settings page code
	 */
	public function show_admin_settings_page() {

		if (!is_admin() || !current_user_can($this->get_management_capability())) return;

		$admin_settings_links = array();
		if (!class_exists('Simba_Two_Factor_Authentication_Premium')) {
			$admin_settings_links[] = array(
				'url'	=> 'https://www.simbahosting.co.uk/s3/product/two-factor-authentication/',
				'title' => __('Premium version', 'two-factor-authentication'),
			);
		}
		$simba_tfa_support_url = apply_filters('simba_tfa_support_url', 'https://wordpress.org/support/plugin/two-factor-authentication/');

		$admin_settings_links[] = array(
			'url'	=> $simba_tfa_support_url,
			'title' => __('Support', 'two-factor-authentication'),
		);
		
		$admin_settings_links[] = array(
			'url'	=> 'https://profiles.wordpress.org/davidanderson#content-plugins',
			'title' => __('More free plugins', 'two-factor-authentication'),
		);
		
		$admin_settings_links[] = array(
			'url'	=> 'http://updraftplus.com',
			'title' => 'UpdraftPlus - '.__('WordPress backups', 'two-factor-authentication'),
		);
		
		$admin_settings_links[] = array(
			'url'	=> 'https://www.simbahosting.co.uk/s3/shop/',
			'title' => __('More premium plugins', 'two-factor-authentication'),
		);
		
		$admin_settings_links[] = array(
			'url'	=> 'https://twitter.com/updraftplus',
			'title' => __('Twitter', 'two-factor-authentication'),
		);
		
		$admin_settings_links[] = array(
			'url'	=> 'https://david.dw-perspective.org.uk',
			'title' => __("Lead developer's homepage", 'two-factor-authentication'),
		);

		$admin_settings_links = apply_filters('simba_tfa_admin_settings_links', $admin_settings_links);

		$this->include_template('admin-settings.php', array(
			'settings_page_heading' => $this->get_settings_page_heading(),
			'admin_settings_links'  => $admin_settings_links,
		));
	}
	
	/**
	 * Runs conditionally on the WP action all_admin_notices
	 */
	public function admin_notice_insufficient_php() {
		/* translators: 1. PHP required  2. PHP version. */
		$this->show_admin_warning('<strong>'.__('Higher PHP version required', 'two-factor-authentication').'</strong><br> '.sprintf(__('The Two Factor Authentication plugin requires PHP version %1$s or higher - your current version is only %2$s.', 'two-factor-authentication'), self::PHP_REQUIRED, PHP_VERSION), 'error');
	}
	
	/**
	 * Runs conditionally on the WP action all_admin_notices
	 */
	public function admin_notice_missing_mcrypt_and_openssl() {
		$this->show_admin_warning('<strong>'.__('PHP OpenSSL or mcrypt module required', 'two-factor-authentication').'</strong><br> '.__('The Two Factor Authentication plugin requires either the PHP openssl (preferred) or mcrypt module to be installed. Please ask your web hosting company to install one of them.', 'two-factor-authentication'), 'error');
	}
	
	/**
	 * Runs conditionally on the WP action all_admin_notices
	 */
	public function admin_notice_missing_db_encryption_key() {
		$this->show_admin_warning('<strong>'.__('Two Factor Authentication encryption key not found', 'two-factor-authentication').'</strong><br> '.htmlspecialchars(__('The "encrypt secrets" feature is currently enabled, but no encryption key has been found (set via the SIMBA_TFA_DB_ENCRYPTION_KEY constant).', 'two-factor-authentication').' '.__('This indicates that either setup failed, or your WordPress installation has been corrupted.', 'two-factor-authentication')) . ' <a href="' . esc_url($this->get_faq_url()) . '">'. __('Go here for the FAQs, which explain how a website owner can de-activate the plugin without needing to login.', 'two-factor-authentication') .'</a>', 'error');
	}

	/**
	 * Run upon the WP plugins_loaded action. This method is called even if main loading aborts - so don't put anything else in it (use a separate method).
	 */
	public function plugins_loaded_load_textdomain() {
		load_plugin_textdomain(
			'two-factor-authentication',
			false,
			dirname(plugin_basename(__FILE__)).'/languages/'
		);
	}
	
	/**
	 * Run upon the WP init action. This method is called even if main loading aborts - so don't put anything else in it (use a separate method).
	 */
	public function init_child() {
		/* translators: %s: plugin version. */
		$this->set_settings_page_heading(sprintf(__('Two Factor Authentication (Version: %s) - Admin Settings', 'two-factor-authentication'), $this->version));
	}
}
endif;

$GLOBALS['simba_two_factor_authentication'] = new Simba_Two_Factor_Authentication_Plugin();

if (file_exists(__DIR__.'/simba-tfa/premium/loader.php') && empty($GLOBALS['simba_two_factor_authentication_premium'])) {
	if (!class_exists('Simba_Two_Factor_Authentication_Premium')) include_once(__DIR__.'/simba-tfa/premium/loader.php');

	$GLOBALS['simba_two_factor_authentication_premium'] = new Simba_Two_Factor_Authentication_Premium($GLOBALS['simba_two_factor_authentication']);

	if (!class_exists('Updraft_Manager_Updater_1_9')) require_once(plugin_dir_path(__FILE__).'vendor/davidanderson684/simba-plugin-manager-updater/class-udm-updater.php');

	try {
		new Updraft_Manager_Updater_1_9('https://www.simbahosting.co.uk/s3', 1, 'two-factor-authentication-premium/two-factor-login.php', array('require_login' => false));
	} catch (Exception $e) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Required for error handling.
		error_log($e->getMessage());
	}
}
