<?php
/**
 * Frontend View Items
 * File: templates/items/view-items.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'orabooks_db_items';

// --- ACTIONS ---

// Delete
if ( isset( $_GET['delete'] ) && isset( $_GET['_wpnonce'] ) ) {
    if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_item_' . $_GET['delete'] ) ) {
        $delete_id = intval( $_GET['delete'] );
        $deleted = $wpdb->delete( $table_name, [ 'id' => $delete_id ] );
        if ( $deleted ) {
            echo '<div class="relative p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg">
                    ✅ Item deleted successfully.
                    <button type="button" class="absolute top-4 right-4 text-green-700 hover:text-green-900 focus:outline-none" onclick="this.parentElement.style.display=\'none\';">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                  </div>';
        } else {
             echo '<div class="relative p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg">
                    ❌ Failed to delete item.
                    <button type="button" class="absolute top-4 right-4 text-red-700 hover:text-red-900 focus:outline-none" onclick="this.parentElement.style.display=\'none\';">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                   </div>';
        }
    }
}

// Search
$search_query = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
$where = '';
if ( ! empty( $search_query ) ) {
    $where = $wpdb->prepare(
        "WHERE i.item_name LIKE %s OR i.item_code LIKE %s OR b.brand_name LIKE %s",
        "%{$search_query}%", "%{$search_query}%", "%{$search_query}%"
    );
}

// Pagination setup
$items_per_page = 20;
$page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$offset = ( $page - 1 ) * $items_per_page;

$total_items = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name $where" );
$total_pages = ceil( $total_items / $items_per_page );

$items = $wpdb->get_results( $wpdb->prepare( 
    "SELECT i.*, b.brand_name as brand, c.category_name as category 
     FROM $table_name i 
     LEFT JOIN {$wpdb->prefix}orabooks_db_brands b ON i.brand_id = b.id 
     LEFT JOIN {$wpdb->prefix}orabooks_db_category c ON i.category_id = c.id 
     $where ORDER BY i.id DESC LIMIT %d OFFSET %d", 
    $items_per_page, $offset 
) );
?>

<div class="bg-white rounded-lg shadow-lg p-4 md:p-6">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between border-b pb-4 mb-6 gap-4">
        <div class="flex items-center">
            <div class="w-10 h-10 md:w-12 md:h-12 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center mr-3 md:mr-4 shadow-sm font-bold">
                <i class="fa-solid fa-list text-lg md:text-xl"></i>
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-gray-800">View Items</h1>
        </div>
        <div class="flex gap-2 w-full md:w-auto">
             <a href="<?php echo esc_url( add_query_arg( 'view', 'import-items' ) ); ?>" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-4 py-2 transition-colors w-full md:w-auto text-center">
                <i class="fa-solid fa-file-import mr-1"></i> Import Items
            </a>
             <a href="<?php echo esc_url( add_query_arg( 'view', 'add-item' ) ); ?>" class="text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2 transition-colors w-full md:w-auto text-center">
                <i class="fa-solid fa-plus mr-1"></i> <span class="hidden sm:inline">Add New</span> Item
            </a>
        </div>
    </div>

    <!-- Search Bar & Export Toolbar -->
    <div class="search-filter-bar mb-4 flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
        <div class="relative flex-1 w-full">
            <div class="absolute inset-y-0 left-0 flex items-center pl-2.5 pointer-events-none">
                <i class="fa-solid fa-search text-gray-400 text-sm"></i>
            </div>
            <input type="search" id="searchInput" value="<?php echo esc_attr( $search_query ); ?>" class="block w-full pl-8 pr-3 py-2 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500" placeholder="Search by name, code, or brand...">
        </div>
        
        <!-- Export & Column Buttons -->
        <div class="export-toolbar flex gap-2 flex-wrap">
            <button id="printBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" title="Print">
                <i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
            </button>
            <button id="pdfBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" title="Export to PDF">
                <i class="fa-solid fa-file-pdf mr-1 text-red-600"></i> <span class="hidden sm:inline">PDF</span>
            </button>
            <button id="excelBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" title="Export to Excel">
                <i class="fa-solid fa-file-excel mr-1 text-green-600"></i> <span class="hidden sm:inline">Excel</span>
            </button>
            <button id="csvBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" title="Export to CSV">
                <i class="fa-solid fa-file-csv mr-1 text-blue-600"></i> <span class="hidden sm:inline">CSV</span>
            </button>
            
            <!-- Column Visibility Dropdown -->
            <div class="relative">
                <button id="columnToggleBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors" title="Toggle Columns">
                    <i class="fa-solid fa-columns mr-1"></i> Columns
                </button>
                <div id="columnDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                    <div class="p-3 space-y-2">
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="0" checked> Image
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="1" checked> Code
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="2" checked> Name
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="3" checked> Brand
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="4" checked> Category/Type
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="5" checked> Stock
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="6" checked> Price
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="7" checked> Status
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive relative overflow-x-auto shadow-md sm:rounded-lg">
        <table id="itemsTable" class="min-w-full divide-y divide-gray-200">
            <thead class="bg-indigo-600 text-white">
                <tr>
                    <th scope="col" class="px-6 py-3">Image</th>
                    <th scope="col" class="px-6 py-3">Code</th>
                    <th scope="col" class="px-6 py-3">Name</th>
                    <th scope="col" class="px-6 py-3">Brand</th>
                    <th scope="col" class="px-6 py-3">Category / Type</th>
                    <th scope="col" class="px-6 py-3">Stock</th>
                    <th scope="col" class="px-6 py-3">Price</th>
                    <th scope="col" class="px-6 py-3">Status</th>
                    <th scope="col" class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $items ) ) : ?>
                    <?php foreach ( $items as $item ) : ?>
                        <tr class="bg-white border-b hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <?php if ( ! empty( $item->item_image ) ) : ?>
                                    <img src="<?php echo esc_url( $item->item_image ); ?>" class="w-10 h-10 rounded object-cover border" alt="Item">
                                <?php else : ?>
                                    <div class="w-10 h-10 rounded bg-gray-200 flex items-center justify-center text-gray-500 text-xs">No Img</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 font-medium"><?php echo esc_html( $item->item_code ); ?></td>
                            <td class="px-6 py-4 text-gray-900 font-semibold"><?php echo esc_html( $item->item_name ); ?></td>
                            <td class="px-6 py-4"><?php echo esc_html( $item->brand ); ?></td>
                            <td class="px-6 py-4">
                                <?php echo esc_html( ( $item->category ?? '' ) . ' / ' . ( $item->item_type ?? '' ) ); ?>
                                <?php if ( ! empty( $item->service_bit ) && $item->service_bit == 1 ) : ?>
                                    <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded ml-1">Service</span>
                                <?php else : ?>
                                    <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2 py-0.5 rounded ml-1">Item</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php 
                                    $stock = floatval( $item->stock );
                                    $alert = floatval( $item->alert_qty );
                                    $class = ( $stock <= $alert ) ? 'text-red-600 font-bold' : 'text-green-600';
                                    echo "<span class='$class'>" . number_format( $stock, 2 ) . "</span>";
                                ?>
                            </td>
                            <td class="px-6 py-4"><?php echo number_format( floatval( $item->sales_price ), 2 ); ?></td>
                            <td class="px-6 py-4">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" class="sr-only peer toggle-status" data-id="<?php echo $item->id; ?>" <?php checked( $item->status, 1 ); ?>>
                                    <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <a href="<?php echo esc_url( add_query_arg( [ 'view' => 'edit-item', 'item_id' => $item->id ] ) ); ?>" class="text-blue-600 hover:text-blue-900 mr-3" title="Edit">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'view' => 'view-items', 'delete' => $item->id ] ), 'delete_item_' . $item->id ) ); ?>" 
                                   class="text-red-600 hover:text-red-900" 
                                   onclick="return confirm('Are you sure you want to delete this item?');" title="Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr class="bg-white border-b">
                        <td colspan="9" class="px-6 py-4 text-center text-gray-500">No items found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ( $total_pages > 1 ) : ?>
        <nav class="flex justify-center mt-6">
            <ul class="inline-flex -space-x-px text-sm">
                <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
                    <li>
                        <a href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>" class="flex items-center justify-center px-3 h-8 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 <?php echo ( $page == $i ) ? 'bg-gray-100 text-gray-700 font-bold' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
jQuery(document).ready(function($) {
    let searchTimeout;
    
    // AJAX Search with debouncing
    $('#searchInput').on('input', function() {
        clearTimeout(searchTimeout);
        const searchQuery = $(this).val();
        
        searchTimeout = setTimeout(function() {
            performSearch(searchQuery);
        }, 500); // Wait 500ms after user stops typing
    });
    
    function performSearch(query) {
        $.ajax({
            url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
            type: 'POST',
            data: {
                action: 'search_items',
                search: query,
                security: '<?php echo wp_create_nonce( 'frontend_ajax_nonce' ); ?>'
            },
            beforeSend: function() {
                $('tbody').html('<tr><td colspan="9" class="px-6 py-4 text-center"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Searching...</td></tr>');
            },
            success: function(response) {
                if(response.success) {
                    updateTable(response.data.items);
                } else {
                    $('tbody').html('<tr><td colspan="9" class="px-6 py-4 text-center text-red-500">Error: ' + response.data.message + '</td></tr>');
                }
            },
            error: function() {
                $('tbody').html('<tr><td colspan="9" class="px-6 py-4 text-center text-red-500">Search failed. Please try again.</td></tr>');
            }
        });
    }
    
    function updateTable(items) {
        let html = '';
        if(items && items.length > 0) {
            items.forEach(function(item) {
                const stock = parseFloat(item.stock);
                const alert = parseFloat(item.alert_qty);
                const stockClass = (stock <= alert) ? 'text-red-600 font-bold' : 'text-green-600';
                const imageHtml = item.item_image ? 
                    '<img src="' + item.item_image + '" class="w-10 h-10 rounded object-cover border" alt="Item">' :
                    '<div class="w-10 h-10 rounded bg-gray-200 flex items-center justify-center text-gray-500 text-xs">No Img</div>';
                const serviceType = item.service_bit == 1 ? 
                    '<span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded ml-1">Service</span>' :
                    '<span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2 py-0.5 rounded ml-1">Item</span>';
                const checked = item.status == 1 ? 'checked' : '';
                
                html += '<tr class="bg-white border-b hover:bg-gray-50">';
                html += '<td class="px-6 py-4">' + imageHtml + '</td>';
                html += '<td class="px-6 py-4 font-medium">' + item.item_code + '</td>';
                html += '<td class="px-6 py-4 text-gray-900 font-semibold">' + item.item_name + '</td>';
                html += '<td class="px-6 py-4">' + (item.brand || '') + '</td>';
                html += '<td class="px-6 py-4">' + (item.category || '') + ' / ' + (item.item_type || '') + ' ' + serviceType + '</td>';
                html += '<td class="px-6 py-4"><span class="' + stockClass + '">' + stock.toFixed(2) + '</span></td>';
                html += '<td class="px-6 py-4">' + parseFloat(item.sales_price).toFixed(2) + '</td>';
                html += '<td class="px-6 py-4">';
                html += '<label class="inline-flex items-center cursor-pointer">';
                html += '<input type="checkbox" class="sr-only peer toggle-status" data-id="' + item.id + '" ' + checked + '>';
                html += '<div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[\'\'] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>';
                html += '</label></td>';
                html += '<td class="px-6 py-4 text-right whitespace-nowrap">';
                html += '<a href="?view=edit-item&item_id=' + item.id + '" class="text-blue-600 hover:text-blue-900 mr-3" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>';
                html += '<a href="#" class="text-red-600 hover:text-red-900 delete-item" data-id="' + item.id + '" title="Delete"><i class="fa-solid fa-trash"></i></a>';
                html += '</td>';
                html += '</tr>';
            });
        } else {
            html = '<tr class="bg-white border-b"><td colspan="9" class="px-6 py-4 text-center text-gray-500">No items found.</td></tr>';
        }
        $('tbody').html(html);
    }
    
    // Toggle item status
    $(document).on('change', '.toggle-status', function() {
        let id = $(this).data('id');
        let status = $(this).is(':checked') ? 1 : 0;
        
        $.ajax({
            url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
            type: 'POST',
            data: {
                action: 'frontend_update_item_status',
                id: id,
                status: status,
                security: '<?php echo wp_create_nonce( 'frontend_ajax_nonce' ); ?>'
            },
            success: function(response) {
                if(!response.success) {
                    alert('Failed to update status');
                }
            }
        });
    });
    
    // Column visibility toggle
    $('#columnToggleBtn').on('click', function(e) {
        e.stopPropagation();
        $('#columnDropdown').toggleClass('hidden');
    });
    
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#columnToggleBtn, #columnDropdown').length) {
            $('#columnDropdown').addClass('hidden');
        }
    });
    
    $('.column-toggle').on('change', function() {
        const column = $(this).data('column');
        const isChecked = $(this).is(':checked');
        
        $('table thead tr th').eq(column).toggle(isChecked);
        $('table tbody tr').each(function() {
            $(this).find('td').eq(column).toggle(isChecked);
        });
    });
    
    // Get table data for export
    function getTableData(format = 'text') {
        const data = [];
        const headers = [];
        
        $('table thead tr th').each(function(index) {
            if($(this).is(':visible') && index < 8) { // Exclude Actions column
                headers.push($(this).text().trim());
            }
        });
        data.push(headers);
        
        $('table tbody tr').each(function() {
            const row = [];
            $(this).find('td').each(function(index) {
                if($(this).is(':visible') && index < 8) {
                    if (index === 0) { // Image column
                        if (format === 'html') {
                            const img = $(this).find('img');
                            if (img.length) {
                                row.push('<img src="' + img.attr('src') + '" style="width:40px;height:40px;border-radius:4px;object-fit:cover;">');
                            } else {
                                row.push('<span style="color:#999;font-size:10px;">No Image</span>');
                            }
                        } else if (format === 'pdf') {
                            const img = $(this).find('img');
                            row.push(img.length ? img[0] : ''); // Pass actual image element
                        } else {
                            row.push(''); // No HTML/Image in Excel/CSV
                        }
                    } else if (index === 7) { // Status column
                        const isChecked = $(this).find('.toggle-status').is(':checked');
                        row.push(isChecked ? 'Active' : 'Inactive');
                    } else {
                        // Create a clone to manipulate and get clean text
                        let $cell = $(this).clone();
                        // Remove hidden elements or elements meant for screen readers only
                        $cell.find('.sr-only, .hidden, script, style').remove();
                        let text = $cell.text().trim();
                        // Clean up text: remove extra spaces and multi-lines
                        text = text.replace(/\s+/g, ' ').trim();
                        row.push(text);
                    }
                }
            });
            if(row.length > 0) {
                data.push(row);
            }
        });
        
        return data;
    }
    
    // Print functionality
    $('#printBtn').on('click', function() {
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Items List</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('body { font-family: Arial, sans-serif; padding: 20px; }');
        printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 20px; }');
        printWindow.document.write('th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }');
        printWindow.document.write('th { background-color: #f3f4f6; font-weight: bold; }');
        printWindow.document.write('h1 { text-align: center; color: #333; margin-bottom: 30px; }');
        printWindow.document.write('img { max-width: 50px; height: auto; border-radius: 4px; }');
        printWindow.document.write('</style></head><body>');
        printWindow.document.write('<h1>Items List</h1>');
        
        const tableData = getTableData('html');
        printWindow.document.write('<table>');
        tableData.forEach(function(row, index) {
            printWindow.document.write('<tr>');
            row.forEach(function(cell) {
                const tag = index === 0 ? 'th' : 'td';
                printWindow.document.write('<' + tag + '>' + cell + '</' + tag + '>');
            });
            printWindow.document.write('</tr>');
        });
        printWindow.document.write('</table></body></html>');
        printWindow.document.close();
        
        // Ensure images are loaded before printing
        setTimeout(function() {
            printWindow.print();
        }, 1000);
    });
    
    // PDF Export
    $('#pdfBtn').on('click', function() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        doc.setFontSize(18);
        doc.text('Items List', 14, 22);
        
        const tableData = getTableData('pdf'); 
        const headers = tableData[0];
        const rows = tableData.slice(1);
        
        doc.autoTable({
            head: [headers],
            body: rows,
            startY: 30,
            theme: 'grid',
            styles: { fontSize: 8, minCellHeight: 14, verticalAlign: 'middle' },
            headStyles: { fillColor: [59, 130, 246] },
            didDrawCell: (data) => {
                // If it's the image column (index 0) and in the body
                if (data.section === 'body' && data.column.index === 0) {
                    const imgContent = data.cell.raw;
                    if (imgContent && (imgContent instanceof HTMLImageElement || typeof imgContent === 'string')) {
                        try {
                            doc.addImage(imgContent, 'JPEG', data.cell.x + 2, data.cell.y + 2, 10, 10);
                        } catch (e) {
                            console.error("PDF Image Error:", e);
                        }
                    }
                    data.cell.text = ''; // Clear text
                }
            }
        });
        
        doc.save('items-list.pdf');
    });
    
    // Excel Export
    $('#excelBtn').on('click', function() {
        const tableData = getTableData('text');
        const ws = XLSX.utils.aoa_to_sheet(tableData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Items');
        XLSX.writeFile(wb, 'items-list.xlsx');
    });
    
    // CSV Export
    $('#csvBtn').on('click', function() {
        const tableData = getTableData('text');
        let csv = '';
        
        tableData.forEach(function(row) {
            csv += row.map(function(cell) {
                return '"' + cell.replace(/"/g, '""') + '"';
            }).join(',') + '\n';
        });
        
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const csvUrl = URL.createObjectURL(blob);
        link.setAttribute('href', csvUrl);
        link.setAttribute('download', 'items-list.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    // Clear delete action from URL after display
    const currentUrl = new URL(window.location.href);
    if (currentUrl.searchParams.has('delete')) {
        currentUrl.searchParams.delete('delete');
        currentUrl.searchParams.delete('_wpnonce');
        window.history.replaceState({}, '', currentUrl.toString());
    }
});
</script>
