<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OBN_Sidebar {
    
    public static function get_menu_tree() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_db_sidebar';
        
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
        
        ob_start();
        foreach ( $menu_tree as $item ) {
            $has_children = ! empty( $item['children'] );
            $active_class = ( $item['menu_slug'] === 'dashboard' ) ? 'active' : '';
            
            if ( ! $has_children ) {
                ?>
                <li>
                    <a href="#" class="obn-nav-link <?php echo $active_class; ?>" data-target="<?php echo esc_attr( $item['menu_slug'] ); ?>">
                        <i class="<?php echo esc_attr( $item['icon'] ); ?> mr-3"></i> 
                        <?php echo esc_html( $item['menu_title'] ); ?>
                    </a>
                </li>
                <?php
            } else {
                ?>
                <li>
                    <a href="#" class="obn-nav-link" data-has-sub="1">
                        <i class="<?php echo esc_attr( $item['icon'] ); ?> mr-3"></i> 
                        <?php echo esc_html( $item['menu_title'] ); ?> <span class="obn-caret">▾</span>
                    </a>
                    <ul class="obn-submenu">
                        <?php foreach ( $item['children'] as $child ) : ?>
                            <li>
                                <a href="#" class="obn-subnav-link" data-target="<?php echo esc_attr( $child['menu_slug'] ); ?>">
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
}
