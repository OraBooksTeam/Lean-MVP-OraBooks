<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'orabooks_db_sidebar';

// Fetch all features for the accounting module, excluding root menus and 'all-features' itself to avoid recursion if desired, 
// but usually, we want all sub-menus.
$user_id = get_current_user_id();
$is_admin = current_user_can( 'manage_options' );
$permitted_ids = [];

if ( class_exists( 'OBN_Permissions' ) ) {
    $permitted_ids = OBN_Permissions::get_user_permitted_ids( $user_id );
}

if ( $permitted_ids === true ) {
    // Admin: Show all accounting features
    $features = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE module = %s AND status = 1 AND menu_slug != 'all-features' GROUP BY menu_slug ORDER BY parent ASC, sort_order ASC", 
        'accounting'
    ));
} elseif ( ! empty( $permitted_ids ) ) {
    // Limited user: Show only permitted items
    $id_list = implode( ',', array_map( 'intval', $permitted_ids ) );
    $features = $wpdb->get_results( 
        "SELECT * FROM $table_name WHERE id IN ($id_list) AND status = 1 AND (module = 'accounting' OR module = 'all') AND menu_slug != 'all-features' GROUP BY menu_slug ORDER BY parent ASC, sort_order ASC"
    );
} else {
    $features = [];
}

// Group by parent if we want a structured view, or just show all.
// Let's show all sub-items (items with a parent > 0) or all clickable items.
?>

<div class="p-6 bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-800">All Accounting Features</h2>
        <p class="text-gray-500">Quick access to all modules and settings.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        <?php foreach ($features as $f): ?>
            <div data-target="<?php echo esc_attr($f->menu_slug); ?>" 
                 class="obn-feature-card cursor-pointer group p-6 bg-blue-50 rounded-2xl border border-blue-100 hover:border-blue-300 hover:bg-blue-100 transition-all duration-300 flex flex-col items-center text-center">
                
                <div class="w-14 h-14 bg-white rounded-xl shadow-sm flex items-center justify-center mb-4 group-hover:scale-110 group-hover:bg-indigo-600 transition-all duration-300">
                    <i class="<?php echo esc_attr($f->icon); ?> text-2xl text-indigo-500 group-hover:text-white"></i>
                </div>
                
                <h3 class="text-lg font-semibold text-gray-800 group-hover:text-indigo-700"><?php echo esc_html($f->menu_title); ?></h3>
                
                <?php if ($f->parent > 0): ?>
                    <span class="mt-1 text-xs font-medium px-2 py-0.5 bg-gray-200 text-gray-600 rounded-full uppercase tracking-wider">Submenu</span>
                <?php else: ?>
                    <span class="mt-1 text-xs font-medium px-2 py-0.5 bg-indigo-100 text-indigo-600 rounded-full uppercase tracking-wider">Main Menu</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.obn-feature-card:active {
    transform: scale(0.98);
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.obn-feature-card').on('click', function() {
        const target = $(this).data('target');
        if (target) {
            window.location.hash = 'view=' + target;
            localStorage.setItem('obn_active_view', target);
        }
        
        // Use the dashboard's showView function if it exists (highly reliable)
        if (typeof showView === 'function') {
            showView('obn-view-' + target);
        } else if (typeof obn_switch_view === 'function') {
            obn_switch_view(target);
        } else {
            // Fallback for standalone use or if showView isn't defined
            $('.obn-view-section').removeClass('active').hide();
            $('#obn-view-' + target).fadeIn(300).addClass('active');
        }
        
        // Scroll to top of content area
        $('.obn-content-area').animate({ scrollTop: 0 }, 'smooth');
    });
});
</script>
