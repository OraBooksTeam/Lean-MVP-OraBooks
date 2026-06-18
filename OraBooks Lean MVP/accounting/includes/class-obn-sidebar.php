<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OBN_Sidebar {
    
    public static function get_menu_tree() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_db_sidebar';

        // Ensure ALL sidebar menu entries are present for Accounting module
        self::ensure_purchase_items( $wpdb, $table_name );
        self::ensure_sales_items( $wpdb, $table_name );
        self::ensure_inventory_items( $wpdb, $table_name );
        self::ensure_contact_items( $wpdb, $table_name );
        // Fix #3: Call the previously missing ensure methods so Accounts, Expense, Assets, and Report menus are created
        self::ensure_account_items( $wpdb, $table_name );
        self::ensure_expense_items( $wpdb, $table_name );
        self::ensure_asset_items( $wpdb, $table_name );
        self::ensure_report_items( $wpdb, $table_name );
        self::ensure_user_items( $wpdb, $table_name );
        self::ensure_fiscal_period_item( $wpdb, $table_name );
        
        // Identify permissions
        $user_id = get_current_user_id();
        $is_admin = current_user_can( 'manage_options' );
        $permitted_ids = [];

        if ( class_exists( 'OBN_Permissions' ) ) {
            $permitted_ids = OBN_Permissions::get_user_permitted_ids( $user_id );
        }

        // Fetch items based on permissions
        if ( $permitted_ids === true ) {
            // Admin with no specific restrictions: show all accounting features
            $results = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM $table_name WHERE status = %d AND (module = 'accounting' OR module = 'all') ORDER BY sort_order ASC",
                1
            ), ARRAY_A );
        } elseif ( ! empty( $permitted_ids ) ) {
            // User (or admin) with explicit permissions: show only those permitted items that belong to this module
            $id_list = implode( ',', array_map( 'intval', $permitted_ids ) );
            $results = $wpdb->get_results( 
                "SELECT * FROM $table_name WHERE id IN ($id_list) AND status = 1 AND (module = 'accounting' OR module = 'all') ORDER BY sort_order ASC", 
                ARRAY_A 
            );
        } else {
            // No permissions and not admin: show nothing
            return [];
        }

        if ( empty( $results ) ) {
            return [];
        }

        $permitted_rows = array();
        $parent_ids_to_force = array();

        // Map permitted items and identify required parents
        foreach ( $results as $row ) {
            $permitted_rows[$row['id']] = $row;
            if ($row['parent'] != 0) {
                $parent_ids_to_force[] = $row['parent'];
            }
        }

        // Add missing parents if any child is permitted (ensure the tree is structural)
        if ( ! empty( $parent_ids_to_force ) ) {
            $parent_ids_to_force = array_unique($parent_ids_to_force);
            $parent_id_list = implode(',', array_map('intval', $parent_ids_to_force));
            // Ensure even forced parents belong to the correct module to prevent cross-module leakage
            $parents = $wpdb->get_results( "SELECT * FROM $table_name WHERE id IN ($parent_id_list) AND status = 1 AND (module = 'accounting' OR module = 'all')", ARRAY_A );
            
            foreach ($parents as $p) {
                if (!isset($permitted_rows[$p['id']])) {
                    $permitted_rows[$p['id']] = $p;
                }
            }
        }
        
        $menu = array();
        $children = array();
        foreach ( $permitted_rows as $row ) {
            if ( $row['parent'] == 0 ) {
                $menu[$row['id']] = $row;
                $menu[$row['id']]['children'] = array();
            } else {
                $children[] = $row;
            }
        }
        
        foreach ( $children as $child ) {
            if ( isset( $menu[$child['parent']] ) ) {
                $menu[$child['parent']]['children'][] = $child;
            }
        }
        
        return $menu;
    }
    
    public static function render_sidebar() {
        $menu_tree = self::get_menu_tree();
        
        if ( empty( $menu_tree ) ) {
            // Fallback or message
            return '<li><p class="px-4 py-2 text-gray-400 italic">No features found.</p></li>';
        }
        
        // Ensure warehouse is visible in Accounting sidebar when the view exists
        $has_warehouse = false;
        foreach ( $menu_tree as $item ) {
            if ( $item['menu_slug'] === 'warehouse' ) {
                $has_warehouse = true;
                break;
            }
            if ( ! empty( $item['children'] ) ) {
                foreach ( $item['children'] as $child ) {
                    if ( $child['menu_slug'] === 'warehouse' ) {
                        $has_warehouse = true;
                        break 2;
                    }
                }
            }
        }

        if ( ! $has_warehouse && defined( 'OBN_ACCOUNTING_PLUGIN_DIR' ) && file_exists( OBN_ACCOUNTING_PLUGIN_DIR . 'templates/settings/warehouse.php' ) ) {
            $menu_tree[999999] = [
                'menu_slug' => 'warehouse',
                'menu_title' => 'Warehouse',
                'icon' => 'fa-solid fa-warehouse',
                'parent' => 0,
                'children' => [],
            ];
        }

        $current_view = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : '';

        ob_start();
        foreach ( $menu_tree as $item ) {
            $has_children = ! empty( $item['children'] );
            $parent_active = false;
            $active_class = '';

            if ( ! $has_children ) {
                $active_class = ( $item['menu_slug'] === $current_view ) ? 'active' : '';
            } else {
                foreach ( $item['children'] as $child ) {
                    if ( $child['menu_slug'] === $current_view ) {
                        $parent_active = true;
                        break;
                    }
                }
                $active_class = $parent_active ? 'active' : '';
            }
            
            if ( ! $has_children ) {
                ?>
                <li>
                    <a href="#" class="obn-nav-link <?php echo esc_attr( $active_class ); ?>" data-target="<?php echo esc_attr( $item['menu_slug'] ); ?>">
                        <i class="<?php echo esc_attr( $item['icon'] ); ?> mr-3"></i> 
                        <?php echo esc_html( $item['menu_title'] ); ?>
                    </a>
                </li>
                <?php
            } else {
                ?>
                <li>
                    <a href="#" class="obn-nav-link <?php echo esc_attr( $active_class ); ?>" data-has-sub="1">
                        <i class="<?php echo esc_attr( $item['icon'] ); ?> mr-3"></i> 
                        <?php echo esc_html( $item['menu_title'] ); ?> <span class="obn-caret">▾</span>
                    </a>
                    <ul class="obn-submenu <?php echo $parent_active ? 'show' : ''; ?>"<?php echo $parent_active ? ' style="display:block;"' : ''; ?> >
                        <?php foreach ( $item['children'] as $child ) :
                            $child_active = ( $child['menu_slug'] === $current_view );
                        ?>
                            <li>
                                <a href="#" class="obn-subnav-link <?php echo $child_active ? 'active' : ''; ?>" data-target="<?php echo esc_attr( $child['menu_slug'] ); ?>">
                                    <i class="<?php echo esc_attr( $child['icon'] ); ?> mr-2"></i> 
                                    <?php echo esc_html( $child['menu_title'] ); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <?php
            }
        }

        return ob_get_clean();
    }

    private static function ensure_account_items($wpdb, $table_name) {
        // Check for Accounts parent
        $parent_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE menu_slug = %s AND module = %s", 'accounts', 'accounting'));
        if ($parent_id) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE menu_slug = %s AND module = %s", 'journal-entry-list', 'accounting'));
            if (!$exists) {
                $wpdb->insert($table_name, [
                    "module" => "accounting", 
                    "parent" => $parent_id, 
                    "menu_title" => "Journal Entry", 
                    "menu_slug" => "journal-entry-list", 
                    "icon" => "fa-solid fa-book-journal-whills", 
                    "sort_order" => 7,
                    "status" => 1
                ]);
            }

            $exists_fy = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE menu_slug = %s AND module = %s", 'fiscal-year-list', 'accounting'));
            if (!$exists_fy) {
                $wpdb->insert($table_name, [
                    "module" => "accounting", 
                    "parent" => $parent_id, 
                    "menu_title" => "Fiscal Year", 
                    "menu_slug" => "fiscal-year-list", 
                    "icon" => "fa-solid fa-calendar-check", 
                    "sort_order" => 8,
                    "status" => 1
                ]);
            }

            $exists_ob = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE menu_slug = %s AND module = %s", 'opening-balance-input', 'accounting'));
            if (!$exists_ob) {
                $wpdb->insert($table_name, [
                    "module" => "accounting", 
                    "parent" => $parent_id, 
                    "menu_title" => "Opening Balance", 
                    "menu_slug" => "opening-balance-input", 
                    "icon" => "fa-solid fa-scale-balanced", 
                    "sort_order" => 9,
                    "status" => 1
                ]);
            }
        }
    }

    private static function ensure_fiscal_period_item($wpdb, $table_name) {
        $parent_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE menu_slug = %s AND module = %s", 'setting', 'accounting'));
        if (!$parent_id) {
            return;
        }

        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE menu_slug = %s AND module = %s", 'fiscal-periods', 'accounting'));
        if (!$exists) {
            $wpdb->insert($table_name, [
                "module" => "accounting",
                "parent" => $parent_id,
                "menu_title" => "Fiscal Periods",
                "menu_slug" => "fiscal-periods",
                "icon" => "fa-solid fa-lock",
                "sort_order" => 8,
                "status" => 1
            ]);
        }
    }

    private static function ensure_expense_items($wpdb, $table_name) {
        // Check for Expense parent
        $parent_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE menu_slug = %s AND module = %s", 'expense', 'accounting'));
        if (!$parent_id) {
             $wpdb->insert($table_name, [
                "module" => "accounting", 
                "parent" => 0, 
                "menu_title" => "Expense", 
                "menu_slug" => "expense", 
                "icon" => "fa-solid fa-receipt", 
                "sort_order" => 7, 
                "status" => 1
             ]);
             $parent_id = $wpdb->insert_id;
        }

        $items = [
            ['menu_title' => 'Expense List', 'menu_slug' => 'expense-list', 'icon' => 'fa-solid fa-circle-dot', 'sort_order' => 1],
            ['menu_title' => 'Category List', 'menu_slug' => 'expense-category', 'icon' => 'fa-solid fa-circle-dot', 'sort_order' => 2],
            ['menu_title' => 'Add Expense', 'menu_slug' => 'expense-add', 'icon' => 'fa-solid fa-circle-plus', 'sort_order' => 3],
        ];

        foreach ($items as $item) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE menu_slug = %s AND module = %s", $item['menu_slug'], 'accounting'));
            if (!$exists) {
                $wpdb->insert($table_name, [
                    "module" => "accounting", 
                    "parent" => $parent_id, 
                    "menu_title" => $item['menu_title'], 
                    "menu_slug" => $item['menu_slug'], 
                    "icon" => $item['icon'], 
                    "sort_order" => $item['sort_order'],
                    "status" => 1
                ]);
            }
        }
    }

    private static function ensure_asset_items($wpdb, $table_name) {
        // Check for Assets parent
        $parent_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE menu_slug = %s AND module = %s", 'assets', 'accounting'));
        if (!$parent_id) {
             $wpdb->insert($table_name, [
                "module" => "accounting", 
                "parent" => 0, 
                "menu_title" => "Assets", 
                "menu_slug" => "assets", 
                "icon" => "fa-solid fa-building-circle-check", 
                "sort_order" => 9, 
                "status" => 1
             ]);
             $parent_id = $wpdb->insert_id;
        }

        $items = [
            ['menu_title' => 'Asset Category', 'menu_slug' => 'asset-category', 'icon' => 'fa-solid fa-tags', 'sort_order' => 1],
            ['menu_title' => 'Asset Register', 'menu_slug' => 'asset-list', 'icon' => 'fa-solid fa-list-check', 'sort_order' => 2],
            ['menu_title' => 'Add Asset', 'menu_slug' => 'asset-add', 'icon' => 'fa-solid fa-plus-circle', 'sort_order' => 3],
            ['menu_title' => 'Disposal Register', 'menu_slug' => 'asset-disposal-list', 'icon' => 'fa-solid fa-trash-can', 'sort_order' => 4],
            ['menu_title' => 'Depreciation Method', 'menu_slug' => 'depreciation-methods', 'icon' => 'fa-solid fa-calculator', 'sort_order' => 5],
        ];

        foreach ($items as $item) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE menu_slug = %s AND module = %s", $item['menu_slug'], 'accounting'));
            if (!$exists) {
                $wpdb->insert($table_name, [
                    "module" => "accounting", 
                    "parent" => $parent_id, 
                    "menu_title" => $item['menu_title'], 
                    "menu_slug" => $item['menu_slug'], 
                    "icon" => $item['icon'], 
                    "sort_order" => $item['sort_order'],
                    "status" => 1
                ]);
            }
        }
    }

	private static function ensure_report_items($wpdb, $table_name) {
        // Check for Accounting Report parent
        $parent_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE (menu_slug = %s OR menu_slug = %s) AND module = %s", 'acc-reports', 'acc-report', 'accounting'));
        if (!$parent_id) {
             $wpdb->insert($table_name, [
                "module" => "accounting", 
                "parent" => 0, 
                "menu_title" => "Accounting Report", 
                "menu_slug" => "acc-reports", 
                "icon" => "fa-solid fa-chart-line", 
                "sort_order" => 11, 
                "status" => 1
             ]);
             $parent_id = $wpdb->insert_id;
        } else {
            // Update existing parent title from old "Acc. Reports" to "Accounting Report"
            $wpdb->update($table_name, ['menu_title' => 'Accounting Report'], ['id' => $parent_id]);
        }

        $items = [
            ['menu_title' => 'Journal Report', 'menu_slug' => 'journal-report', 'icon' => 'fa-solid fa-book', 'sort_order' => 1],
            ['menu_title' => 'Ledger Report', 'menu_slug' => 'ledger-report', 'icon' => 'fa-solid fa-book-journal-whills', 'sort_order' => 2],
            ['menu_title' => 'Trial Balance', 'menu_slug' => 'trial-balance-report', 'icon' => 'fa-solid fa-scale-balanced', 'sort_order' => 3],
            ['menu_title' => 'Income Statement', 'menu_slug' => 'income-statement-report', 'icon' => 'fa-solid fa-file-invoice-dollar', 'sort_order' => 4],
            ['menu_title' => 'Balance Sheet', 'menu_slug' => 'balance-sheet-report', 'icon' => 'fa-solid fa-scale-unbalanced', 'sort_order' => 5],
        ];

        foreach ($items as $item) {
            $exists_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE menu_slug = %s AND module = %s", $item['menu_slug'], 'accounting'));
            if (!$exists_id) {
                $wpdb->insert($table_name, [
                    "module" => "accounting", 
                    "parent" => $parent_id, 
                    "menu_title" => $item['menu_title'], 
                    "menu_slug" => $item['menu_slug'], 
                    "icon" => $item['icon'], 
                    "sort_order" => $item['sort_order'],
                    "status" => 1
                ]);
            } else {
                $wpdb->update($table_name, ['sort_order' => $item['sort_order'], 'parent' => $parent_id, 'menu_title' => $item['menu_title']], ['id' => $exists_id]);
            }
        }
    }

    public static function ensure_purchase_items( $wpdb, $table_name ) {
        // Check for Purchase parent
        $parent_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE menu_slug = %s AND module = %s", 'purchase', 'accounting' ) );
        if ( ! $parent_id ) {
            $wpdb->insert( $table_name, [
                'module'     => 'accounting',
                'parent'     => 0,
                'menu_title' => 'Purchase',
                'menu_slug'  => 'purchase',
                'icon'       => 'fa-solid fa-bag-shopping',
                'sort_order' => 6,
                'status'     => 1,
            ] );
            $parent_id = $wpdb->insert_id;

            // Shift Quotation and subsequent items down to make space for Purchase under Advance (Advance = 5, Purchase = 6, Quotation = 7, etc.)
            $wpdb->query( $wpdb->prepare(
                "UPDATE $table_name SET sort_order = sort_order + 1 WHERE module = %s AND parent = 0 AND sort_order >= 6 AND menu_slug != 'purchase'",
                'accounting'
            ) );
        }

        $items = [
            ['menu_title' => 'View Purchase', 'menu_slug' => 'view-purchase', 'icon' => 'fa-solid fa-file-invoice-dollar', 'sort_order' => 1],
            ['menu_title' => 'Add Purchase', 'menu_slug' => 'add-purchase', 'icon' => 'fa-solid fa-bag-shopping', 'sort_order' => 2],
            ['menu_title' => 'Edit Purchase', 'menu_slug' => 'edit-purchase', 'icon' => 'fa-solid fa-pen-to-square', 'sort_order' => 3],
            ['menu_title' => 'Purchase Orders', 'menu_slug' => 'purchase-ordered-list', 'icon' => 'fa-solid fa-clipboard-list', 'sort_order' => 4],
            ['menu_title' => 'Pending Purchases', 'menu_slug' => 'purchase-pending-list', 'icon' => 'fa-solid fa-clock', 'sort_order' => 5],
            ['menu_title' => 'Purchase Invoice', 'menu_slug' => 'purchase-invoice', 'icon' => 'fa-solid fa-file-invoice', 'sort_order' => 6],
            ['menu_title' => 'Purchase Return List', 'menu_slug' => 'purchase-return-list', 'icon' => 'fa-solid fa-arrow-rotate-left', 'sort_order' => 7],
            ['menu_title' => 'Add Purchase Return', 'menu_slug' => 'add-purchase-return', 'icon' => 'fa-solid fa-plus', 'sort_order' => 8],
            ['menu_title' => 'Edit Purchase Return', 'menu_slug' => 'edit-purchase-return', 'icon' => 'fa-solid fa-pen-to-square', 'sort_order' => 9],
            ['menu_title' => 'Purchase Return Invoice', 'menu_slug' => 'purchase-return-invoice', 'icon' => 'fa-solid fa-file-invoice-dollar', 'sort_order' => 10],
        ];

        foreach ( $items as $item ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE menu_slug = %s AND module = %s AND parent = %d", $item['menu_slug'], 'accounting', $parent_id ) );
            if ( ! $exists ) {
                $wpdb->insert( $table_name, [
                    'module'     => 'accounting',
                    'parent'     => $parent_id,
                    'menu_title' => $item['menu_title'],
                    'menu_slug'  => $item['menu_slug'],
                    'icon'       => $item['icon'],
                    'sort_order' => $item['sort_order'],
                    'status'     => 1,
                ] );
            } else {
                $wpdb->update( $table_name, [
                    'parent'     => $parent_id,
                    'menu_title' => $item['menu_title'],
                    'icon'       => $item['icon'],
                    'sort_order' => $item['sort_order'],
                ], [ 'menu_slug' => $item['menu_slug'], 'module' => 'accounting', 'parent' => $parent_id ] );
            }
        }
    }

    public static function ensure_inventory_items( $wpdb, $table_name ) {
        // Check for Items parent
        $parent_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE menu_slug = %s AND module = %s", 'items', 'accounting' ) );
        if ( ! $parent_id ) {
            $wpdb->insert( $table_name, [
                'module'     => 'accounting',
                'parent'     => 0,
                'menu_title' => 'Items',
                'menu_slug'  => 'items',
                'icon'       => 'fa-solid fa-boxes-stacked',
                'sort_order' => 6,
                'status'     => 1,
            ] );
            $parent_id = $wpdb->insert_id;

            // Shift Purchase and subsequent items down to make space for Items under Advance (Advance = 5, Items = 6, Purchase = 7, etc.)
            $wpdb->query( $wpdb->prepare(
                "UPDATE $table_name SET sort_order = sort_order + 1 WHERE module = %s AND parent = 0 AND sort_order >= 6 AND menu_slug NOT IN ('items', 'purchase')",
                'accounting'
            ) );
        }

        $items = [
            ['menu_title' => 'View Items', 'menu_slug' => 'view-items', 'icon' => 'fa-solid fa-list', 'sort_order' => 1],
            ['menu_title' => 'Add Item', 'menu_slug' => 'add-item', 'icon' => 'fa-solid fa-plus', 'sort_order' => 2],
            ['menu_title' => 'Edit Item', 'menu_slug' => 'edit-item', 'icon' => 'fa-solid fa-pen-to-square', 'sort_order' => 3],
            ['menu_title' => 'Add Service', 'menu_slug' => 'add-service', 'icon' => 'fa-solid fa-wrench', 'sort_order' => 4],
            ['menu_title' => 'Import Items', 'menu_slug' => 'import-items', 'icon' => 'fa-solid fa-file-import', 'sort_order' => 5],
            ['menu_title' => 'Variants List', 'menu_slug' => 'variants-list', 'icon' => 'fa-solid fa-code-branch', 'sort_order' => 6],
            ['menu_title' => 'Print Labels', 'menu_slug' => 'print-labels', 'icon' => 'fa-solid fa-tag', 'sort_order' => 7],
            ['menu_title' => 'Categories', 'menu_slug' => 'categories', 'icon' => 'fa-solid fa-tags', 'sort_order' => 8],
            ['menu_title' => 'Brands', 'menu_slug' => 'brands', 'icon' => 'fa-solid fa-tag', 'sort_order' => 9],
            ['menu_title' => 'Units', 'menu_slug' => 'units', 'icon' => 'fa-solid fa-ruler', 'sort_order' => 10],
        ];

        foreach ( $items as $item ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE menu_slug = %s AND module = %s AND parent = %d", $item['menu_slug'], 'accounting', $parent_id ) );
            if ( ! $exists ) {
                $wpdb->insert( $table_name, [
                    'module'     => 'accounting',
                    'parent'     => $parent_id,
                    'menu_title' => $item['menu_title'],
                    'menu_slug'  => $item['menu_slug'],
                    'icon'       => $item['icon'],
                    'sort_order' => $item['sort_order'],
                    'status'     => 1,
                ] );
            } else {
                $wpdb->update( $table_name, [
                    'parent'     => $parent_id,
                    'menu_title' => $item['menu_title'],
                    'icon'       => $item['icon'],
                    'sort_order' => $item['sort_order'],
                ], [ 'menu_slug' => $item['menu_slug'], 'module' => 'accounting', 'parent' => $parent_id ] );
            }
        }
    }

    private static function ensure_contact_items( $wpdb, $table_name ) {
        // Check for Contact parent
        $parent_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE menu_slug = %s AND module = %s", 'contact', 'accounting' ) );
        if ( ! $parent_id ) {
            // Shift items at sort_order >= 7 down by 1
            $wpdb->query( $wpdb->prepare(
                "UPDATE $table_name SET sort_order = sort_order + 1 WHERE module = %s AND parent = 0 AND sort_order >= 7",
                'accounting'
            ) );

            $wpdb->insert( $table_name, [
                'module'     => 'accounting',
                'parent'     => 0,
                'menu_title' => 'Contact',
                'menu_slug'  => 'contact',
                'icon'       => 'fa-solid fa-address-book',
                'sort_order' => 7,
                'status'     => 1,
            ] );
            $parent_id = $wpdb->insert_id;
        }

        $items = [
            ['menu_title' => 'Customers', 'menu_slug' => 'customers', 'icon' => 'fa-solid fa-users', 'sort_order' => 1],
            ['menu_title' => 'Suppliers', 'menu_slug' => 'suppliers', 'icon' => 'fa-solid fa-truck', 'sort_order' => 2],
            ['menu_title' => 'Customer Payments', 'menu_slug' => 'customer-pay', 'icon' => 'fa-solid fa-money-bill-wave', 'sort_order' => 3],
            ['menu_title' => 'Supplier Payments', 'menu_slug' => 'supplier-pay', 'icon' => 'fa-solid fa-money-bill-transfer', 'sort_order' => 4],
            ['menu_title' => 'Import Customers', 'menu_slug' => 'import-customers', 'icon' => 'fa-solid fa-file-import', 'sort_order' => 5],
            ['menu_title' => 'Import Suppliers', 'menu_slug' => 'import-suppliers', 'icon' => 'fa-solid fa-file-import', 'sort_order' => 6],
        ];

        foreach ( $items as $item ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE menu_slug = %s AND module = %s AND parent = %d", $item['menu_slug'], 'accounting', $parent_id ) );
            if ( ! $exists ) {
                $wpdb->insert( $table_name, [
                    'module'     => 'accounting',
                    'parent'     => $parent_id,
                    'menu_title' => $item['menu_title'],
                    'menu_slug'  => $item['menu_slug'],
                    'icon'       => $item['icon'],
                    'sort_order' => $item['sort_order'],
                    'status'     => 1,
                ] );
            } else {
                $wpdb->update( $table_name, [
                    'parent'     => $parent_id,
                    'menu_title' => $item['menu_title'],
                    'icon'       => $item['icon'],
                    'sort_order' => $item['sort_order'],
                ], [ 'menu_slug' => $item['menu_slug'], 'module' => 'accounting', 'parent' => $parent_id ] );
            }
        }
    }

    private static function ensure_sales_items( $wpdb, $table_name ) {
        $parent_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE menu_slug = %s AND module = %s", 'sales', 'accounting' ) );
        if ( ! $parent_id ) {
            $wpdb->insert( $table_name, [
                'module'     => 'accounting',
                'parent'     => 0,
                'menu_title' => 'Sales',
                'menu_slug'  => 'sales',
                'icon'       => 'fa-solid fa-cart-shopping',
                'sort_order' => 7,
                'status'     => 1,
            ] );
            $parent_id = $wpdb->insert_id;

            $wpdb->query( $wpdb->prepare(
                "UPDATE $table_name SET sort_order = sort_order + 1 WHERE module = %s AND parent = 0 AND sort_order >= 7 AND menu_slug != 'sales'",
                'accounting'
            ) );
        }

        $items = [
            ['menu_title' => 'View Sales', 'menu_slug' => 'view-sales', 'icon' => 'fa-solid fa-file-invoice', 'sort_order' => 1],
            ['menu_title' => 'Add Sale', 'menu_slug' => 'add-sale', 'icon' => 'fa-solid fa-cart-plus', 'sort_order' => 2],
            ['menu_title' => 'POS Sale', 'menu_slug' => 'pos-sale', 'icon' => 'fa-solid fa-cash-register', 'sort_order' => 3],
            ['menu_title' => 'Sales Orders', 'menu_slug' => 'sales-order-list', 'icon' => 'fa-solid fa-clipboard-list', 'sort_order' => 4],
            ['menu_title' => 'Pending Delivery', 'menu_slug' => 'sales-pending-delivery', 'icon' => 'fa-solid fa-clock', 'sort_order' => 5],
            ['menu_title' => 'Sales Invoice', 'menu_slug' => 'sales-invoice', 'icon' => 'fa-solid fa-file-invoice', 'sort_order' => 6],
            ['menu_title' => 'Edit Sales', 'menu_slug' => 'edit-sales', 'icon' => 'fa-solid fa-pen-to-square', 'sort_order' => 7],
            ['menu_title' => 'Sales Return List', 'menu_slug' => 'sales-return-list', 'icon' => 'fa-solid fa-arrow-rotate-left', 'sort_order' => 8],
            ['menu_title' => 'Add Sales Return', 'menu_slug' => 'add-sales-return', 'icon' => 'fa-solid fa-plus', 'sort_order' => 9],
            ['menu_title' => 'Edit Sales Return', 'menu_slug' => 'edit-sales-return', 'icon' => 'fa-solid fa-pen-to-square', 'sort_order' => 10],
            ['menu_title' => 'Sales Return Invoice', 'menu_slug' => 'sales-return-invoice', 'icon' => 'fa-solid fa-file-invoice', 'sort_order' => 11],
        ];

        foreach ( $items as $item ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE menu_slug = %s AND module = %s AND parent = %d", $item['menu_slug'], 'accounting', $parent_id ) );
            if ( ! $exists ) {
                $wpdb->insert( $table_name, [
                    'module'     => 'accounting',
                    'parent'     => $parent_id,
                    'menu_title' => $item['menu_title'],
                    'menu_slug'  => $item['menu_slug'],
                    'icon'       => $item['icon'],
                    'sort_order' => $item['sort_order'],
                    'status'     => 1,
                ] );
            } else {
                $wpdb->update( $table_name, [
                    'parent'     => $parent_id,
                    'menu_title' => $item['menu_title'],
                    'icon'       => $item['icon'],
                    'sort_order' => $item['sort_order'],
                ], [ 'menu_slug' => $item['menu_slug'], 'module' => 'accounting', 'parent' => $parent_id ] );
            }
        }
    }

    private static function ensure_user_items($wpdb, $table_name) {
        $parent_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE menu_slug = %s AND module = %s", 'users', 'accounting'));
        if (!$parent_id) {
            $wpdb->insert($table_name, [
                'module' => 'accounting',
                'parent' => 0,
                'menu_title' => 'Users',
                'menu_slug' => 'users',
                'icon' => 'fa-solid fa-users-gear',
                'sort_order' => 100,
                'status' => 1,
            ]);
            $parent_id = $wpdb->insert_id;
        }

        $items = [
            ['menu_title' => 'Roles', 'menu_slug' => 'roles', 'icon' => 'fa-solid fa-user-tag', 'sort_order' => 1],
            ['menu_title' => 'Employees', 'menu_slug' => 'employees', 'icon' => 'fa-solid fa-users', 'sort_order' => 2],
            ['menu_title' => 'Permissions', 'menu_slug' => 'ac-permissions', 'icon' => 'fa-solid fa-shield-halved', 'sort_order' => 3],
        ];

        foreach ($items as $item) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE menu_slug = %s AND module = %s", $item['menu_slug'], 'accounting'));
            if (!$exists) {
                $wpdb->insert($table_name, [
                    'module' => 'accounting',
                    'parent' => $parent_id,
                    'menu_title' => $item['menu_title'],
                    'menu_slug' => $item['menu_slug'],
                    'icon' => $item['icon'],
                    'sort_order' => $item['sort_order'],
                    'status' => 1,
                ]);
            }
        }
    }
}
