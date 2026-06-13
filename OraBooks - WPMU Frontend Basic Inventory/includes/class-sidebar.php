<?php
if (!defined('ABSPATH')) {
    exit;
}

class Frontend_Inventory_Sidebar
{

    public static function get_menu_tree()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_db_sidebar';

        // Fetch all active items, excluding structural views that should not appear in the sidebar.
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE status = %d AND (module = 'inventory' OR module = 'all') AND menu_slug NOT IN ('edit-item', 'sales-invoice', 'purchase-invoice', 'edit-sales', 'edit-purchase', 'add-sales-return', 'edit-sales-return', 'sales-return-invoice', 'add-purchase-return', 'edit-purchase-return', 'purchase-return-invoice', 'add-adjustment', 'edit-adjustment', 'adjustment-invoice', 'add-transfer', 'edit-transfer', 'transfer-invoice', 'add-employee', 'edit-employee') ORDER BY sort_order ASC",
            1
        ), ARRAY_A);

        if (empty($results)) {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE status = %d AND (module = 'inventory' OR module = 'all') ORDER BY sort_order ASC",
                1
            ), ARRAY_A);
        }

        // Filter by permissions
        $user_id = get_current_user_id();
        $permitted_ids = Frontend_Inventory_Permissions::get_user_permitted_ids($user_id);

        if ($permitted_ids !== true && empty($permitted_ids)) {
            return array();
        }

        $menu = array();
        $permitted_ids_int = array_map('intval', (array) $permitted_ids);

        // Final permitted rows. Do not add IDs that are absent from the user's saved permissions.
        $permitted_rows = array();
        foreach ($results as $row) {
            if ($permitted_ids === true || in_array((int) $row['id'], $permitted_ids_int, true)) {
                $permitted_rows[$row['id']] = $row;
            }
        }

        if (empty($permitted_rows)) {
            return array();
        }

        $children = array();
        foreach ($permitted_rows as $row) {
            if ($row['parent'] == 0) {
                $menu[$row['id']] = $row;
                $menu[$row['id']]['children'] = array();
            } else {
                $children[] = $row;
            }
        }

        foreach ($children as $child) {
            if (isset($menu[$child['parent']])) {
                $menu[$child['parent']]['children'][] = $child;
            }
        }

        return $menu;
    }

    public static function render_sidebar($current_view)
    {
        $menu_tree = self::get_menu_tree();

        if (empty($menu_tree)) {
            return '<li><p class="px-4 py-2 text-gray-400 italic">No dynamic features found.</p></li>';
        }

        ob_start();
        foreach ($menu_tree as $item) {
            $has_children = !empty($item['children']);
            $is_active = ($current_view === $item['menu_slug']);

            // Check if any child is active
            if ($has_children) {
                foreach ($item['children'] as $child) {
                    if ($current_view === $child['menu_slug']) {
                        $is_active = true;
                        break;
                    }
                }
            }

            if (!$has_children) {
                ?>
                <li>
                    <a href="<?php echo esc_url(add_query_arg('view', $item['menu_slug'])); ?>"
                        class="inv-nav-link flex items-center px-4 py-3 <?php echo $is_active ? 'active' : ''; ?> rounded-md text-white hover:bg-gray-700 transition-colors">
                        <i class="<?php echo esc_attr($item['icon']); ?> w-5 text-center mr-2"></i>
                        <span class="font-medium"><?php echo esc_html($item['menu_title']); ?></span>
                    </a>
                </li>
                <?php
            } else {
                ?>
                <li>
                    <button
                        class="w-full flex items-center justify-between px-3 py-3 text-white hover:bg-gray-700 hover:text-white rounded-md transition-colors focus:outline-none inventory-menu-toggle <?php echo $is_active ? 'active' : ''; ?>"
                        <?php echo $is_active ? 'aria-expanded="true"' : 'aria-expanded="false"'; ?>>
                        <span class="flex items-center">
                            <i class="<?php echo esc_attr($item['icon']); ?> w-5 text-center mr-2"></i>
                            <span class="font-medium"><?php echo esc_html($item['menu_title']); ?></span>
                        </span>
                        <i class="fa-solid fa-chevron-down menu-arrow <?php echo $is_active ? 'rotate-180' : ''; ?>"></i>
                    </button>
                    <ul class="inv-submenu submenu bg-gray-900 rounded-md mt-1 space-y-1 pl-8 pr-2 pb-2 <?php echo $is_active ? 'open' : ''; ?>"
                        style="<?php echo $is_active ? 'max-height: 1000px;' : ''; ?>">
                        <?php foreach ($item['children'] as $child):
                            $child_active = ($current_view === $child['menu_slug']);
                            ?>
                            <li>
                                <a href="<?php echo esc_url(add_query_arg('view', $child['menu_slug'])); ?>"
                                    class="inv-subnav-link block py-2 text-sm text-gray-400 hover:text-white <?php echo $child_active ? 'active text-white font-medium' : ''; ?>">
                                    <i class="<?php echo esc_attr($child['icon']); ?>"></i>
                                    <?php echo esc_html($child['menu_title']); ?>
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
}
