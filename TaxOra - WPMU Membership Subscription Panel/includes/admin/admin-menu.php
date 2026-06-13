<?php
// Admin menu setup
add_action( 'admin_menu', 'orabooks_admin_menu' );
function orabooks_admin_menu() {
    add_menu_page( 
        'Orabooks Membership', 
        'TOB Membership', 
        'manage_options', 
        'orabooks-membership', 
        'orabooks_dashboard_page', 
        'dashicons-book', 
        56 
    );
    
    add_submenu_page( 
        'orabooks-membership', 
        'Dashboard', 
        'Dashboard', 
        'manage_options', 
        'orabooks-membership', 
        'orabooks_dashboard_page' 
    );
    
    add_submenu_page( 
        'orabooks-membership', 
        'Groups', 
        'Groups', 
        'manage_options', 
        'orabooks-membership-groups', 
        'orabooks_groups_page' 
    );
    
    add_submenu_page( 
        'orabooks-membership', 
        'Members', 
        'Members', 
        'manage_options', 
        'orabooks-membership-members', 
        'orabooks_members_page' 
    );
    
    add_submenu_page( 
        'orabooks-membership', 
        'Subscribers', 
        'Subscribers', 
        'manage_options', 
        'orabooks-membership-subscribers', 
        'orabooks_subscribers_page' 
    );
    
    add_submenu_page( 
        'orabooks-membership', 
        'Plans', 
        'Plans', 
        'manage_options', 
        'orabooks-membership-levels', 
        'orabooks_levels_page' 
    );
    
    add_submenu_page( 
        'orabooks-membership', 
        'Orders', 
        'Orders', 
        'manage_options', 
        'orabooks-membership-orders', 
        'orabooks_orders_page' 
    );
    
    add_submenu_page( 
        'orabooks-membership', 
        'Reports', 
        'Reports', 
        'manage_options', 
        'orabooks-membership-reports', 
        'orabooks_reports_page' 
    );
    
    
    add_submenu_page( 
        'orabooks-membership', 
        'Settings', 
        'Settings', 
        'manage_options', 
        'orabooks-membership-settings', 
        'orabooks_settings_page' 
    );
}


// Admin tabs navigation
function orabooks_admin_tabs( $current = 'dashboard' ) {
    $tabs = array(
        'dashboard' => array('Dashboard', 'orabooks-membership'),
        'groups' => array('Groups', 'orabooks-membership-groups'),
        'members' => array('Members', 'orabooks-membership-members'),
        'subscribers' => array('Subscribers', 'orabooks-membership-subscribers'),
        'levels' => array('Plans', 'orabooks-membership-levels'),
        'orders' => array('Orders', 'orabooks-membership-orders'),
        'reports' => array('Reports', 'orabooks-membership-reports'),
        'settings' => array('Settings', 'orabooks-membership-settings')
    );

    echo '<h2 class="nav-tab-wrapper orabooks-admin-tabs">';
    
    foreach ( $tabs as $slug => $tab ) {
        $class = ( $slug === $current ) ? 'nav-tab nav-tab-active' : 'nav-tab';
        $url = admin_url( 'admin.php?page=' . $tab[1] );
        
        echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $tab[0] ) . '</a>';
    }
    
    echo '</h2>';
}
