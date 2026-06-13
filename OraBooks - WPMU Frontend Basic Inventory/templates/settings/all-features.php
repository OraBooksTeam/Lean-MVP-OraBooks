<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'orabooks_db_sidebar';

// Fetch all features for the inventory module, excluding root menus, 'all-features', and 'edit-item' itself
$features = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $table_name WHERE module = %s AND status = 1 AND menu_slug NOT IN ('edit-item') ORDER BY parent ASC, sort_order ASC", 
    'inventory'
));

?>

<div class="p-6 bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">All Inventory Features</h2>
            <p class="text-gray-500">Quick access to all modules and settings.</p>
        </div>
        <div class="flex gap-2">
            <button id="printBtn" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg transition-colors flex items-center gap-2">
                <i class="fa-solid fa-print"></i> Print
            </button>
            <button id="pdfBtn" class="bg-red-50 hover:bg-red-100 text-red-600 px-4 py-2 rounded-lg transition-colors flex items-center gap-2">
                <i class="fa-solid fa-file-pdf"></i> PDF
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        <?php foreach ($features as $f): 
            $view_slug = $f->menu_slug;
            
            // If it's a root menu that has children, point it to the first child (often the list view)
            if ($f->parent == 0) {
                $first_child = $wpdb->get_row($wpdb->prepare(
                    "SELECT menu_slug FROM $table_name WHERE parent = %d AND status = 1 ORDER BY sort_order ASC LIMIT 1",
                    $f->id
                ));
                if ($first_child) {
                    $view_slug = $first_child->menu_slug;
                }
            }
        ?>
            <a href="<?php echo esc_url(add_query_arg('view', $view_slug)); ?>" 
               class="inv-feature-card group p-6 bg-slate-50 rounded-2xl border border-slate-100 hover:border-indigo-300 hover:bg-indigo-50 transition-all duration-300 flex flex-col items-center text-center no-underline decoration-transparent">
                
                <div class="w-14 h-14 bg-white rounded-xl shadow-sm flex items-center justify-center mb-4 group-hover:scale-110 group-hover:bg-indigo-600 transition-all duration-300">
                    <i class="<?php echo esc_attr($f->icon); ?> text-2xl text-indigo-500 group-hover:text-white"></i>
                </div>
                
                <h3 class="text-lg font-semibold text-gray-800 group-hover:text-indigo-700"><?php echo esc_html($f->menu_title); ?></h3>
                
                <?php if ($f->parent > 0): ?>
                    <span class="mt-1 text-xs font-medium px-2 py-0.5 bg-gray-200 text-gray-600 rounded-full uppercase tracking-wider">Submenu</span>
                <?php else: ?>
                    <span class="mt-1 text-xs font-medium px-2 py-0.5 bg-indigo-100 text-indigo-600 rounded-full uppercase tracking-wider">Main Module</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Hidden table for exports -->
    <div class="hidden">
        <table id="featuresTable" class="w-full">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Feature</th>
                    <th>Slug</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($features as $idx => $f): ?>
                    <tr>
                        <td><?php echo $idx + 1; ?></td>
                        <td><?php echo esc_html($f->menu_title); ?></td>
                        <td><?php echo esc_html($f->menu_slug); ?></td>
                        <td><?php echo $f->parent > 0 ? 'Submenu' : 'Main Menu'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.inv-feature-card:active {
    transform: scale(0.98);
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
<script>
jQuery(document).ready(function($) {
    $('#printBtn').on('click', function() {
        var w = window.open('', '_blank');
        w.document.write('<html><head><title>Inventory Features</title>');
        w.document.write('<style>table{width:100%;border-collapse:collapse;font-family:sans-serif;}th,td{border:1px solid #ddd;padding:12px;text-align:left;}th{background:#f8fafc;}</style>');
        w.document.write('</head><body>');
        w.document.write('<h1 style="text-align:center;">All Inventory Features</h1>');
        w.document.write($('#featuresTable').prop('outerHTML'));
        w.document.write('</body></html>');
        w.document.close();
        w.print();
        w.close();
    });

    $('#pdfBtn').on('click', function() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        doc.setFontSize(18);
        doc.text("All Inventory Features", 14, 20);
        doc.setFontSize(11);
        doc.setTextColor(100);
        doc.text("Generated on: " + new Date().toLocaleDateString(), 14, 28);
        
        doc.autoTable({ 
            html: '#featuresTable',
            startY: 35,
            headStyles: { fillColor: [79, 70, 229] },
            alternateRowStyles: { fillColor: [249, 250, 251] }
        });
        doc.save('inventory-features.pdf');
    });
});
</script>
