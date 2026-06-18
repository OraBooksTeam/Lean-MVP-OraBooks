<?php
/**
 * Plugin Name: OraBooks - WPMU Frontend Basic Accounting
 * Plugin URI: https://www.enest.com.bd/plugins/wpmu-tob-febacc
 * Description: Advanced accounting workspace addon for OraBooks Lean MVP (sales, purchase, inventory, GL).
 * Version: 1/25
 * Author: Engr. AnwarIT CASDP and Farid Ahmed
 * Author URI: https://www.anwarit.com
 * Creditse: TOB Developer Team
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpmu tob febacc
 *
 * Minimum PHP: 8.0.30
 * Minimum WP: 6.8
 * @author 		Engr.AnwarIT- CASDP/TaxOra
 * @package		WPMU Default Logo
 *
 * @copyright 	Copyright (c) 2025, TaxOra LLC USA
 * Orabooks Addon: true
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Fix for PHP Notice: ob_end_flush(): failed to send buffer of zlib output compression (0)
 * This occurs when zlib compression is enabled and WordPress tries to flush all buffers at shutdown.
 */
/*
if (!has_action('shutdown', 'wp_ob_end_flush_all_safe')) {
	remove_action('shutdown', 'wp_ob_end_flush_all', 1);
	add_action('shutdown', 'wp_ob_end_flush_all_safe', 1);
}

if (!function_exists('wp_ob_end_flush_all_safe')) {
	function wp_ob_end_flush_all_safe()
	{
		$levels = ob_get_level();
		for ($i = 0; $i < $levels; $i++) {
			if (ob_get_length() > 0) {
				@ob_end_flush();
			} else {
				@ob_end_clean();
			}
		}
	}
}
*/
define('OBN_ACCOUNTING_VERSION', '1.0.1');
define('OBN_ACCOUNTING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OBN_ACCOUNTING_PLUGIN_URL', plugin_dir_url(__FILE__));

// Fallback function for Accounting module fallbacks if deactivated
function obn_accounting_declare_accounting_fallbacks()
{
	// Define fallback constants for Accounting module if it is not active
	if (!defined('FRONTEND_ACCOUNTING_TEMPLATE_PATH')) {
		define('FRONTEND_ACCOUNTING_TEMPLATE_PATH', OBN_ACCOUNTING_PLUGIN_DIR . 'templates/');
	}
	if (!defined('FRONTEND_ACCOUNTING_PATH')) {
		define('FRONTEND_ACCOUNTING_PATH', OBN_ACCOUNTING_PLUGIN_DIR);
	}

	// Fallback class for Frontend_Accounting_Permissions if Accounting is deactivated
	if (!class_exists('Frontend_Accounting_Permissions')) {
		class Frontend_Accounting_Permissions
		{
			public static function has_view_permission($view)
			{
				if (current_user_can('manage_options')) {
					return true;
				}
				if (class_exists('OBN_Permissions')) {
					return OBN_Permissions::has_view_permission($view);
				}
				return false;
			}
		}
	}

	// Fallback function for orabooks_can_access_inventory if Inventory is deactivated
	// if ( ! function_exists( 'orabooks_can_access_inventory' ) ) {
	// 	function orabooks_can_access_inventory() {
	// 		if ( ! is_user_logged_in() ) {
	// 			return false;
	// 		}
	// 		if ( current_user_can( 'manage_options' ) ) {
	// 			return true;
	// 		}
	// 		if ( class_exists( 'OBN_Permissions' ) ) {
	// 			return OBN_Permissions::has_view_permission( 'view-purchase' ) || OBN_Permissions::has_view_permission( 'add-purchase' );
	// 		}
	// 		return false;
	// 	}
	// }
}
add_action('plugins_loaded', 'obn_accounting_declare_accounting_fallbacks', 1);

if (!function_exists('orabooks_can_access_accounting')) {
	function orabooks_can_access_accounting()
	{
		if (!is_user_logged_in()) {
			return false;
		}
		if (current_user_can('manage_options')) {
			return true;
		}
		// if ( function_exists( 'orabooks_can_access_inventory' ) && orabooks_can_access_inventory() ) {
		// 	return true;
		// }
		if (class_exists('OBN_Auth')) {
			$auth = new OBN_Auth();
			if ($auth->can_access_accounting()) {
				return true;
			}
		}
		return false;
	}
}

/**
 * Check for OraBooks Membership Plugin
 */

function obn_accounting_check_dependency()
{
	// Lean MVP core plugin is the required host platform.
	if (
		defined('ORABOOKS_VERSION') ||
		class_exists('OraBooks_Database') ||
		class_exists('OraBooks_Auth')
	) {
		return true;
	}

	if (!function_exists('is_plugin_active')) {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$active_plugins = (array) get_option('active_plugins', array());
	if (is_multisite()) {
		$network_active = (array) get_site_option('active_sitewide_plugins', array());
		$active_plugins = array_merge($active_plugins, array_keys($network_active));
	}

	foreach ($active_plugins as $plugin_path) {
		if (stripos($plugin_path, 'orabooks') !== false && stripos($plugin_path, 'lean') !== false) {
			return true;
		}
		if (basename(dirname($plugin_path)) === 'OraBooks Lean MVP') {
			return true;
		}
	}

	return false;
}

function obn_accounting_core_notice()
{
	echo '<div class="notice notice-error is-dismissible"><p>' .
		esc_html__('OraBooks - WPMU Frontend Basic Accounting requires the OraBooks Lean MVP plugin to be installed and activated.', 'wpmu tob febacc') .
		'</p></div>';
}

/**
 * Enforce dependency: Deactivate if membership plugin is missing
 */

function obn_accounting_enforce_dependency()
{
	// Only run this in admin and if we're not currently activating/deactivating
	if (!is_admin() || !current_user_can('activate_plugins')) {
		return;
	}

	// Delay check until all plugins are loaded (admin_init is late enough)
	if (!obn_accounting_check_dependency()) {
		$plugin_file = plugin_basename(__FILE__);
		if (is_plugin_active($plugin_file)) {
			deactivate_plugins($plugin_file);
			add_action('admin_notices', 'obn_accounting_core_notice');
			add_action('network_admin_notices', 'obn_accounting_core_notice');
			// Suppress the "Plugin activated" message
			if (isset($_GET['activate'])) {
				unset($_GET['activate']);
			}
		}
	}
}
add_action('admin_init', 'obn_accounting_enforce_dependency');

/**
 * Register as Orabooks Addon
 */
function obn_register_accounting_addon()
{
	if (function_exists('orabooks_register_addon')) {
		orabooks_register_addon(array(
			'id' => 'accounting',
			'name' => 'Frontend Accounting',
			'description' => 'Full double-entry accounting workspace aligned with OraBooks Lean MVP tenancy and RBAC.',
			'version' => OBN_ACCOUNTING_VERSION,
			'plugin_file' => __FILE__,
			'author' => 'Orabooks',
			'features' => array(
				'accounting' => array(
					'name' => 'Accounting System',
					'description' => 'Access to full accounting dashboard (Income, Expenses, Reports).',
					'icon' => '📊',
					'category' => 'business',
					'subdomain_path' => '/accounting'
				)
			)
		));
	}
}
add_action('orabooks_register_addons', 'obn_register_accounting_addon');
add_action('plugins_loaded', 'obn_register_accounting_addon', 20);

/**
 * The code that runs during plugin activation.
 */
function activate_obn_frontend_accounting($network_wide)
{
	// Check dependency immediately
	if (!obn_accounting_check_dependency()) {
		return;
	}

	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-activator.php';

	if (is_multisite() && $network_wide) {
		$sites = get_sites();
		foreach ($sites as $site) {
			switch_to_blog($site->blog_id);
			OBN_Activator::activate();
			restore_current_blog();
		}
	} else {
		OBN_Activator::activate();
	}
}

function obn_frontend_accounting_new_blog($blog_id, $user_id, $domain, $path, $site_id, $meta)
{
	if (is_plugin_active_for_network(plugin_basename(__FILE__))) {
		switch_to_blog($blog_id);
		require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-activator.php';
		OBN_Activator::activate();
		restore_current_blog();
	}
}
add_action('wpmu_new_blog', 'obn_frontend_accounting_new_blog', 10, 6);

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_obn_frontend_accounting()
{
	// Optional: Cleanup if needed, but usually we keep data.
}

register_activation_hook(__FILE__, 'activate_obn_frontend_accounting');
register_deactivation_hook(__FILE__, 'deactivate_obn_frontend_accounting');


function obn_accounting_maybe_create_tables()
{
	if (!obn_accounting_check_dependency()) {
		return;
	}
	global $wpdb;
	$prefix = $wpdb->prefix;
	$required = array(
		$wpdb->prefix . 'orabooks_db_users',
		$wpdb->prefix . 'orabooks_db_userswarehouses',
		$wpdb->prefix . 'orabooks_db_variants',
		$wpdb->prefix . 'orabooks_db_warehouse',
		$wpdb->prefix . 'orabooks_db_warehouseitems',
		$wpdb->prefix . 'orabooks_temp_holdinvoice',
		$wpdb->prefix . 'orabooks_ac_accounts',
		$wpdb->prefix . 'orabooks_ac_moneydeposits',
		$wpdb->prefix . 'orabooks_ac_moneytransfer',
		$wpdb->prefix . 'orabooks_ac_transactions',
		$wpdb->prefix . 'orabooks_ci_sessions',
		$wpdb->prefix . 'orabooks_db_bankdetails',
		$wpdb->prefix . 'orabooks_db_brands',
		$wpdb->prefix . 'orabooks_db_category',
		$wpdb->prefix . 'orabooks_db_cobpayments',
		$wpdb->prefix . 'orabooks_db_company',
		$wpdb->prefix . 'orabooks_db_country',
		$wpdb->prefix . 'orabooks_db_coupons',
		$wpdb->prefix . 'orabooks_db_currency',
		$wpdb->prefix . 'orabooks_db_custadvance',
		$wpdb->prefix . 'orabooks_db_customers',
		$wpdb->prefix . 'orabooks_db_customer_coupons',
		$wpdb->prefix . 'orabooks_db_customer_payments',
		$wpdb->prefix . 'orabooks_db_emailtemplates',
		$wpdb->prefix . 'orabooks_db_expense',
		$wpdb->prefix . 'orabooks_db_expense_items',
		$wpdb->prefix . 'orabooks_db_expense_category',
		$wpdb->prefix . 'orabooks_db_fivemojo',
		$wpdb->prefix . 'orabooks_db_hold',
		$wpdb->prefix . 'orabooks_db_holditems',
		$wpdb->prefix . 'orabooks_db_instamojo',
		$wpdb->prefix . 'orabooks_db_instamojopayments',
		$wpdb->prefix . 'orabooks_db_items',
		$wpdb->prefix . 'orabooks_db_languages',
		$wpdb->prefix . 'orabooks_db_package',
		$wpdb->prefix . 'orabooks_db_paymenttypes',
		$wpdb->prefix . 'orabooks_db_paypal',
		$wpdb->prefix . 'orabooks_db_paypalpaylog',
		$wpdb->prefix . 'orabooks_db_permissions',
		$wpdb->prefix . 'orabooks_db_purchase',
		$wpdb->prefix . 'orabooks_db_purchaseitems',
		$wpdb->prefix . 'orabooks_db_purchaseitemsreturn',
		$wpdb->prefix . 'orabooks_db_purchasepayments',
		$wpdb->prefix . 'orabooks_db_purchasepaymentsreturn',
		$wpdb->prefix . 'orabooks_db_purchasereturn',
		$wpdb->prefix . 'orabooks_db_quotation',
		$wpdb->prefix . 'orabooks_db_quotationitems',
		$wpdb->prefix . 'orabooks_db_roles',
		$wpdb->prefix . 'orabooks_db_sales',
		$wpdb->prefix . 'orabooks_db_salesitems',
		$wpdb->prefix . 'orabooks_db_salesitemsreturn',
		$wpdb->prefix . 'orabooks_db_salespayments',
		$wpdb->prefix . 'orabooks_db_salespaymentsreturn',
		$wpdb->prefix . 'orabooks_db_salesreturn',
		$wpdb->prefix . 'orabooks_db_shippingaddress',
		$wpdb->prefix . 'orabooks_db_sitesettings',
		$wpdb->prefix . 'orabooks_db_smsapi',
		$wpdb->prefix . 'orabooks_db_smstemplates',
		$wpdb->prefix . 'orabooks_db_sobpayments',
		$wpdb->prefix . 'orabooks_db_states',
		$wpdb->prefix . 'orabooks_db_stockadjustment',
		$wpdb->prefix . 'orabooks_db_stockadjustmentitems',
		$wpdb->prefix . 'orabooks_db_stockentry',
		$wpdb->prefix . 'orabooks_db_stocktransfer',
		$wpdb->prefix . 'orabooks_db_stocktransferitems',
		$wpdb->prefix . 'orabooks_db_store',
		$wpdb->prefix . 'orabooks_db_stripe',
		$wpdb->prefix . 'orabooks_db_stripepayments',
		$wpdb->prefix . 'orabooks_db_subscription',
		$wpdb->prefix . 'orabooks_db_suppliers',
		$wpdb->prefix . 'orabooks_db_supplier_payments',
		$wpdb->prefix . 'orabooks_db_tax',
		$wpdb->prefix . 'orabooks_db_timezone',
		$wpdb->prefix . 'orabooks_db_twilio',
		$wpdb->prefix . 'orabooks_db_units',
		$wpdb->prefix . 'orabooks_ac_coa_types',
		$wpdb->prefix . 'orabooks_ac_coa_list',
		$wpdb->prefix . 'orabooks_ac_journal_entry',
		$wpdb->prefix . 'orabooks_ac_journal_line',
		$wpdb->prefix . 'orabooks_db_sidebar',
		$wpdb->prefix . 'orabooks_ac_opening_balances',
		$wpdb->prefix . 'orabooks_ac_inventory_opening',
		$wpdb->prefix . 'orabooks_ac_assets',
		$wpdb->prefix . 'orabooks_ac_depreciation_records',
		$wpdb->prefix . 'orabooks_ac_asset_disposals',
		$wpdb->prefix . 'orabooks_ac_depreciation_methods',
		$wpdb->prefix . 'fiscal_periods',
		$wpdb->prefix . 'orabooks_ac_audit_events',
	);
	$missing = false;
	foreach ($required as $table) {
		if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
			$missing = true;
			break;
		}
	}
	if ($missing) {
		require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-activator.php';
		OBN_Activator::activate();
	}
}
// add_action( 'plugins_loaded', 'obn_accounting_maybe_create_tables', 1 );

/**
 * Initialize the plugin classes.
 */
function run_obn_frontend_accounting()
{
	if (!obn_accounting_check_dependency()) {
		return;
	}

	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-lean-mvp-bridge.php';
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/adapters/class-obn-org-context.php';
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/adapters/class-obn-lean-mvp-adapters.php';

	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-activator.php';
	OBN_Activator::maybe_upgrade_schema();

	// Session start moved to init hook

	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-auth.php';
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-shortcodes.php';
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-currency.php';
	// Tax handlers (copied from Orabooks Accounts pattern)
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-tax.php';
	// Payment types handlers
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-paymenttypes.php';
	// Accounts handlers
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-accounts.php';
	// Deposits handlers
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-deposits.php';
	// Money Transfer handler
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-money-transfer.php';
	// Advances handlers
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-advances.php';
	// Items handlers
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-items.php';
	// Variants handlers
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-variants.php';
	// Print Labels handlers
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-print-labels.php';
	// Quotations handlers
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-quotations.php';
	// Expenses handlers
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-expenses.php';
	// Expense Categories handlers
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-expense-categories.php';
	// Coupons handlers
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-coupons.php';
	// Reports handlers
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-reports.php';
	// Assets handlers
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-assets.php';
	// Permissions handlers
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-permissions.php';
	// Roles handlers (Accounting)
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-roles.php';
	// Employees handlers (Accounting)
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-employees.php';
	// Accounting Permissions (Role-based)
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-accounting-permissions.php';
	// Journal Entries handlers
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-journal-entries.php';
	// Reimbursement handlers
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-reimbursement.php';
	// Fiscal Year handlers
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-fiscal-year.php';
	// Fiscal Period & Lock Governance handlers (SL-304)
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-fiscal-periods.php';
	// Opening Balance handlers
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-opening-balance.php';
	// Warehouse handler
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-warehouse.php';
	// Category, Brand, Unit, Tax modal handlers (for Items section quick-add modals)
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-categories.php';
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-brands.php';
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-units.php';
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-taxes.php';
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-sidebar.php';
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-dashboard.php';
	// Contacts (Customers, Suppliers, Payments, Imports)
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-contacts.php';

	// Load purchases & purchase return handlers safely
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-purchases.php';
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-purchasereturn.php';
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-sales.php';
	require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-salesreturn.php';

	// Files are loaded, logic will be initialized on 'init'
}
add_action('plugins_loaded', 'run_obn_frontend_accounting');

/**
 * Start session safely on init
 */
function obn_accounting_init_sessions()
{
	if (!session_id() && !headers_sent()) {
		@session_start();
	}
}
add_action('init', 'obn_accounting_init_sessions', 1);

/**
 * Initialize logic on proper hook where Auth is available
 */
function obn_accounting_init_logic()
{
	if (!obn_accounting_check_dependency()) {
		return;
	}
	// Ensure sidebar table and accounting data exist
	global $wpdb;
	$table_sidebar = $wpdb->prefix . 'orabooks_db_sidebar';
	$table_assets = $wpdb->prefix . 'orabooks_ac_assets';
	// Fix #4: Use correct table name for user permissions (not role permissions)
	$table_user_permissions = $wpdb->prefix . 'orabooks_user_permissions';

	$sidebar_exists = !empty($wpdb->get_var("SHOW TABLES LIKE '$table_sidebar'"));
	$assets_exists = !empty($wpdb->get_var("SHOW TABLES LIKE '$table_assets'"));
	$user_permissions_exists = !empty($wpdb->get_var("SHOW TABLES LIKE '$table_user_permissions'"));
	$accounting_data_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE module = %s", 'accounting')) : 0;
	$user_permissions_data_exists = $user_permissions_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table_user_permissions") : 0;
	$fiscal_year_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE menu_slug = %s AND module = %s", 'fiscal-year-list', 'accounting')) : 0;
	$expense_add_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE menu_slug = %s AND module = %s", 'expense-add', 'accounting')) : 0;

	static $obn_activated = false;
	if ($obn_activated)
		return;
	$obn_activated = true;

	if (!$sidebar_exists || !$assets_exists || !$user_permissions_exists || ($accounting_data_exists == 0) || ($user_permissions_data_exists == 0)) {
		require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-activator.php';
		OBN_Activator::activate();
	}


	// Enqueue Select2 assets for accounting pages
	function orabooks_accounting_enqueue_assets()
	{
		// Load on the Add Sale and Edit Sale pages where Select2 is used
		if (isset($_GET['view']) && (strpos($_GET['view'], 'add-sale') !== false || strpos($_GET['view'], 'edit-sales') !== false)) {
			wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0');
			wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);
		}
	}
	add_action('wp_enqueue_scripts', 'orabooks_accounting_enqueue_assets');


	$auth = new OBN_Auth();
	// Only access session if started
	if (session_id()) {
		$_SESSION['obn_accountant_logged_in'] = $auth->can_access_accounting() ? 1 : 0;
	} else {
		$_SESSION['obn_accountant_logged_in'] = 0;
	}
	$dashboard = new OBN_Dashboard();
	$shortcodes = new OBN_Shortcodes($auth, $dashboard);
}
add_action('init', 'obn_accounting_init_logic', 20);
