<?php
/**
 * Unified Frontend Accounting module (merged WPMU Basic Accounting into Lean MVP).
 *
 * @package OraBooks
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Accounting {
    private static $booted = false;

    public static function init() {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        self::define_constants();
        define('ORABOOKS_ACCOUNTING_LOADED', true);

        self::declare_fallbacks();
        self::load_modules();

        add_action('init', [__CLASS__, 'init_sessions'], 1);
        add_action('init', [__CLASS__, 'init_logic'], 20);
        add_action('wpmu_new_blog', [__CLASS__, 'on_new_blog'], 10, 6);
        add_action('orabooks_register_addons', [__CLASS__, 'register_feature'], 5);
    }

    public static function define_constants() {
        if (!defined('OBN_ACCOUNTING_VERSION')) {
            define('OBN_ACCOUNTING_VERSION', ORABOOKS_VERSION);
        }
        if (!defined('OBN_ACCOUNTING_PLUGIN_DIR')) {
            define('OBN_ACCOUNTING_PLUGIN_DIR', ORABOOKS_PLUGIN_DIR . 'accounting/');
        }
        if (!defined('OBN_ACCOUNTING_PLUGIN_URL')) {
            define('OBN_ACCOUNTING_PLUGIN_URL', ORABOOKS_PLUGIN_URL . 'accounting/');
        }
    }

    public static function register_feature() {
        if (!function_exists('orabooks_register_addon')) {
            return;
        }

        orabooks_register_addon([
            'id' => 'accounting',
            'name' => 'Advanced Accounting',
            'description' => 'Full double-entry accounting workspace (sales, purchase, inventory, GL, reports).',
            'version' => OBN_ACCOUNTING_VERSION,
            'plugin_file' => ORABOOKS_PLUGIN_DIR . 'orabooks.php',
            'author' => 'OraBooks',
            'builtin' => true,
            'features' => [
                'accounting' => [
                    'name' => 'Accounting System',
                    'description' => 'Access to full accounting dashboard (Income, Expenses, Reports).',
                    'icon' => '📊',
                    'category' => 'business',
                    'subdomain_path' => '/accounting',
                ],
            ],
        ]);
    }

    public static function activate($network_wide = false) {
        self::define_constants();

        if (!class_exists('OBN_Activator')) {
            require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-activator.php';
        }

        if (is_multisite() && $network_wide) {
            foreach (get_sites() as $site) {
                switch_to_blog($site->blog_id);
                OBN_Activator::activate();
                restore_current_blog();
            }
            return;
        }

        OBN_Activator::activate();
    }

    public static function on_new_blog($blog_id, $user_id, $domain, $path, $site_id, $meta) {
        if (!is_plugin_active_for_network(plugin_basename(ORABOOKS_PLUGIN_DIR . 'orabooks.php'))) {
            return;
        }

        switch_to_blog($blog_id);
        self::activate(false);
        restore_current_blog();
    }

    public static function declare_fallbacks() {
        if (!defined('FRONTEND_ACCOUNTING_TEMPLATE_PATH')) {
            define('FRONTEND_ACCOUNTING_TEMPLATE_PATH', OBN_ACCOUNTING_PLUGIN_DIR . 'templates/');
        }
        if (!defined('FRONTEND_ACCOUNTING_PATH')) {
            define('FRONTEND_ACCOUNTING_PATH', OBN_ACCOUNTING_PLUGIN_DIR);
        }

        if (!class_exists('Frontend_Accounting_Permissions')) {
            class Frontend_Accounting_Permissions {
                public static function has_view_permission($view) {
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

        if (!function_exists('orabooks_can_access_accounting')) {
            function orabooks_can_access_accounting() {
                if (!is_user_logged_in()) {
                    return false;
                }
                if (current_user_can('manage_options')) {
                    return true;
                }
                if (class_exists('OBN_Auth')) {
                    $auth = new OBN_Auth();
                    if ($auth->can_access_accounting()) {
                        return true;
                    }
                }
                return false;
            }
        }
    }

    public static function load_modules() {
        if (!is_dir(OBN_ACCOUNTING_PLUGIN_DIR . 'includes')) {
            return;
        }

        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-lean-mvp-bridge.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/adapters/class-obn-org-context.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/adapters/class-obn-lean-mvp-adapters.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-activator.php';

        OBN_Activator::maybe_upgrade_schema();

        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-auth.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-shortcodes.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-currency.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-tax.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-paymenttypes.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-accounts.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-deposits.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-money-transfer.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-advances.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-items.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-variants.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-print-labels.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-quotations.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-expenses.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-expense-categories.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-coupons.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-reports.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-assets.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-permissions.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-roles.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-employees.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-accounting-permissions.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-journal-entries.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-reimbursement.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-fiscal-year.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-fiscal-periods.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-opening-balance.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-warehouse.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-categories.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-brands.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-units.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-taxes.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-sidebar.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-dashboard.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-contacts.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-purchases.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-purchasereturn.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-sales.php';
        require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-salesreturn.php';
    }

    public static function init_sessions() {
        if (!session_id() && !headers_sent()) {
            @session_start();
        }
    }

    public static function init_logic() {
        global $wpdb;

        $table_sidebar = $wpdb->prefix . 'orabooks_db_sidebar';
        $table_assets = $wpdb->prefix . 'orabooks_ac_assets';
        $table_user_permissions = $wpdb->prefix . 'orabooks_user_permissions';

        $sidebar_exists = !empty($wpdb->get_var("SHOW TABLES LIKE '$table_sidebar'"));
        $assets_exists = !empty($wpdb->get_var("SHOW TABLES LIKE '$table_assets'"));
        $user_permissions_exists = !empty($wpdb->get_var("SHOW TABLES LIKE '$table_user_permissions'"));
        $accounting_data_exists = $sidebar_exists
            ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE module = %s", 'accounting'))
            : 0;
        $user_permissions_data_exists = $user_permissions_exists
            ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_user_permissions")
            : 0;

        static $activated = false;
        if ($activated) {
            return;
        }
        $activated = true;

        if (
            !$sidebar_exists
            || !$assets_exists
            || !$user_permissions_exists
            || $accounting_data_exists === 0
            || $user_permissions_data_exists === 0
        ) {
            if (!class_exists('OBN_Activator')) {
                require_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/class-obn-activator.php';
            }
            OBN_Activator::activate();
        }

        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_select2_for_sales']);

        $auth = new OBN_Auth();
        if (session_id()) {
            $_SESSION['obn_accountant_logged_in'] = $auth->can_access_accounting() ? 1 : 0;
        } else {
            $_SESSION['obn_accountant_logged_in'] = 0;
        }

        $dashboard = new OBN_Dashboard();
        new OBN_Shortcodes($auth, $dashboard);
    }

    public static function enqueue_select2_for_sales() {
        if (!isset($_GET['view'])) {
            return;
        }

        $view = sanitize_text_field(wp_unslash($_GET['view']));
        if (strpos($view, 'add-sale') === false && strpos($view, 'edit-sales') === false) {
            return;
        }

        wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');
        wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
    }
}
