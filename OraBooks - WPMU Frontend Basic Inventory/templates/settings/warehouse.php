<?php
/**
 * Frontend Warehouse Management
 * File: templates/settings/warehouse.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'orabooks_db_warehouse';

// --- Logic Wrapper ---
$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
$msg    = '';

// Handle redirect messages
if ( isset( $_GET['message'] ) ) {
    if ( $_GET['message'] === 'added' ) {
        $msg = '<div class="relative p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg shadow-sm border border-green-200 message-alert" role="alert">
                    <i class="fa-solid fa-check-circle mr-2"></i>Warehouse added successfully.
                    <button type="button" class="absolute top-4 right-4 text-green-700 hover:text-green-900 focus:outline-none" onclick="this.parentElement.style.display=\'none\';">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>';
    } elseif ( $_GET['message'] === 'updated' ) {
        $msg = '<div class="relative p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg shadow-sm border border-green-200 message-alert" role="alert">
                    <i class="fa-solid fa-check-circle mr-2"></i>Warehouse updated successfully.
                    <button type="button" class="absolute top-4 right-4 text-green-700 hover:text-green-900 focus:outline-none" onclick="this.parentElement.style.display=\'none\';">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>';
    }
}

// 1. DELETE Action
if ( $action === 'delete' && isset( $_GET['id'] ) && isset( $_GET['_wpnonce'] ) ) {
    if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_warehouse_' . $_GET['id'] ) ) {
        $id = intval( $_GET['id'] );
        $deleted = $wpdb->delete( $table_name, [ 'id' => $id ] );
        if ( $deleted ) {
            $msg = '<div class="relative p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg message-alert" role="alert">
                        Warehouse deleted successfully.
                        <button type="button" class="absolute top-4 right-4 text-green-700 hover:text-green-900 focus:outline-none" onclick="this.parentElement.style.display=\'none\';">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>';
        } else {
            $msg = '<div class="relative p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg message-alert" role="alert">
                        Failed to delete warehouse.
                        <button type="button" class="absolute top-4 right-4 text-red-700 hover:text-red-900 focus:outline-none" onclick="this.parentElement.style.display=\'none\';">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>';
        }
    } else {
        $msg = '<div class="relative p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg message-alert" role="alert">
                    Security check failed.
                    <button type="button" class="absolute top-4 right-4 text-red-700 hover:text-red-900 focus:outline-none" onclick="this.parentElement.style.display=\'none\';">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>';
    }
    // Fallback to list view
    $action = 'list';
}

// 2. SAVE Action (Insert/Update)
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['save_warehouse_nonce'] ) && wp_verify_nonce( $_POST['save_warehouse_nonce'], 'save_warehouse_action' ) ) {
    
    $warehouse_name = sanitize_text_field( $_POST['warehouse_name'] );
    $mobile         = sanitize_text_field( $_POST['mobile'] );
    $email          = sanitize_email( $_POST['email'] );
    $address        = sanitize_textarea_field( $_POST['address'] );
    $id             = isset( $_POST['warehouse_id'] ) ? intval( $_POST['warehouse_id'] ) : 0;

    // Duplicate Check
    $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE warehouse_name = %s AND id != %d", $warehouse_name, $id ) );

    if ( empty( $warehouse_name ) ) {
        $msg = '<div class="relative p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg message-alert" role="alert">
                    Warehouse Name is required.
                    <button type="button" class="absolute top-4 right-4 text-red-700 hover:text-red-900 focus:outline-none" onclick="this.parentElement.style.display=\'none\';">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>';
    } elseif ( $existing ) {
        $msg = '<div class="relative p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg message-alert" role="alert">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i>Duplicate Entry: A warehouse with this name already exists.
                    <button type="button" class="absolute top-4 right-4 text-red-700 hover:text-red-900 focus:outline-none" onclick="this.parentElement.style.display=\'none\';">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>';
    } else {
        $data = [
            'warehouse_name' => $warehouse_name,
            'mobile'         => $mobile,
            'email'          => $email,
            'address'        => $address,
        ];

        if ( $id > 0 ) {
            // Update
            $updated = $wpdb->update( $table_name, $data, [ 'id' => $id ] );
            if ( $updated !== false ) {
                $redirect_url = add_query_arg( [ 'action' => 'list', 'message' => 'updated' ], remove_query_arg( [ 'action', 'id', 'message' ] ) );
                echo '<script>window.location.href = "' . esc_url_raw( $redirect_url ) . '";</script>';
                exit;
            } else {
                $msg = '<div class="relative p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg message-alert" role="alert">
                            Failed to update warehouse.
                            <button type="button" class="absolute top-4 right-4 text-red-700 hover:text-red-900 focus:outline-none" onclick="this.parentElement.style.display=\'none\';">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>';
            }
        } else {
            // Insert
            $data['store_id']       = 1; // Default
            $data['warehouse_type'] = 'custom';
            $data['status']         = 1;
            $data['created_date']   = current_time( 'mysql' );

            $inserted = $wpdb->insert( $table_name, $data );
            if ( $inserted ) {
                $redirect_url = add_query_arg( [ 'action' => 'list', 'message' => 'added' ], remove_query_arg( [ 'action', 'id', 'message' ] ) );
                echo '<script>window.location.href = "' . esc_url_raw( $redirect_url ) . '";</script>';
                exit;
            } else {
                $msg = '<div class="relative p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg message-alert" role="alert">
                            Failed to add warehouse.
                            <button type="button" class="absolute top-4 right-4 text-red-700 hover:text-red-900 focus:outline-none" onclick="this.parentElement.style.display=\'none\';">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>';
            }
        }
    }
}

// --- VIEWS ---

// VIEW: ADD / EDIT FORM
if ( $action === 'add' || $action === 'edit' ) {
    $edit_data = null;
    $form_title = "Add New Warehouse";
    $btn_text   = "Save Warehouse";
    
    if ( $action === 'edit' && isset( $_GET['id'] ) ) {
        $id = intval( $_GET['id'] );
        $edit_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id=%d", $id ) );
        if ( $edit_data ) {
            $form_title = "Edit Warehouse";
            $btn_text   = "Update Warehouse";
        } else {
            echo '<div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">Warehouse not found.</div>';
            $action = 'list'; // Fallback
        }
    }
    
    if ( $action !== 'list' ) : 
    ?>
    <div class="bg-white rounded-lg shadow-lg p-6 max-w-2xl mx-auto">
        <div class="flex items-center justify-between border-b pb-4 mb-6">
            <h1 class="text-2xl font-bold text-gray-800"><?php echo esc_html( $form_title ); ?></h1>
            <a href="<?php echo esc_url( add_query_arg( [ 'view' => 'warehouse', 'action' => 'list' ] ) ); ?>" class="text-gray-500 hover:text-gray-700">
                <i class="fa-solid fa-arrow-left mr-1"></i> Back to List
            </a>
        </div>
        
        <?php echo $msg; ?>

        <form method="post">
            <?php wp_nonce_field( 'save_warehouse_action', 'save_warehouse_nonce' ); ?>
            <input type="hidden" name="warehouse_id" value="<?php echo esc_attr( $edit_data->id ?? 0 ); ?>">
            
            <div class="mb-4">
                <label class="block mb-2 text-sm font-medium text-gray-900">Warehouse Name <span class="text-red-500">*</span></label>
                <input type="text" name="warehouse_name" value="<?php echo esc_attr( $edit_data->warehouse_name ?? '' ); ?>" required class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full py-2 px-2.5">
            </div>
            
            <div class="mb-4">
                <label class="block mb-2 text-sm font-medium text-gray-900">Mobile</label>
                <input type="text" name="mobile" value="<?php echo esc_attr( $edit_data->mobile ?? '' ); ?>" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full py-2 px-2.5">
            </div>
            
            <div class="mb-6">
                <label class="block mb-2 text-sm font-medium text-gray-900">Email</label>
                <input type="email" name="email" value="<?php echo esc_attr( $edit_data->email ?? '' ); ?>" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full py-2 px-2.5">
            </div>
            
            <div class="mb-6">
                <label class="block mb-2 text-sm font-medium text-gray-900">Address</label>
                <textarea name="address" rows="3" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full py-2 px-2.5"><?php echo esc_textarea( $edit_data->address ?? '' ); ?></textarea>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center transition-colors">
                    <?php echo esc_html( $btn_text ); ?>
                </button>
            </div>
        </form>
    </div>
    <?php 
    endif;
}

// VIEW: LIST
if ( $action === 'list' ) {
    $warehouses = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id DESC" );
    ?>
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex items-center justify-between border-b pb-4 mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Warehouses List</h1>
            <a href="<?php echo esc_url( add_query_arg( [ 'view' => 'warehouse', 'action' => 'add' ] ) ); ?>" class="text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2 transition-colors">
                <i class="fa-solid fa-plus mr-1"></i> Add New Warehouse
            </a>
        </div>

        <?php echo $msg; ?>

        <!-- Search Bar & Export Toolbar -->
        <div class="search-filter-bar mb-4 flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
            <div class="relative flex-1 w-full">
                <div class="absolute inset-y-0 left-0 flex items-center pl-2.5 pointer-events-none">
                    <i class="fa-solid fa-search text-gray-400 text-sm"></i>
                </div>
                <input type="search" id="searchInput" class="block w-full pl-8 pr-3 py-2 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500" placeholder="Search warehouses...">
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
                                <input type="checkbox" class="column-toggle mr-2" data-column="0" checked> #
                            </label>
                            <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                                <input type="checkbox" class="column-toggle mr-2" data-column="1" checked> Warehouse Name
                            </label>
                            <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                                <input type="checkbox" class="column-toggle mr-2" data-column="2" checked> Mobile
                            </label>
                            <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                                <input type="checkbox" class="column-toggle mr-2" data-column="3" checked> Email
                            </label>
                            <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                                <input type="checkbox" class="column-toggle mr-2" data-column="4" checked> Address
                            </label>
                            <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                                <input type="checkbox" class="column-toggle mr-2" data-column="5" checked> Status
                            </label>
                            <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                                <input type="checkbox" class="column-toggle mr-2" data-column="6" checked> Actions
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="relative overflow-x-auto">
            <table id="warehouseTable" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-indigo-600 text-white">
                    <tr>
                        <th scope="col" class="px-6 py-2">#</th>
                        <th scope="col" class="px-6 py-2">Warehouse Name</th>
                        <th scope="col" class="px-6 py-2">Mobile</th>
                        <th scope="col" class="px-6 py-2">Email</th>
                        <th scope="col" class="px-6 py-2">Address</th>
                        <th scope="col" class="px-6 py-2">Status</th>
                        <th scope="col" class="px-6 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $warehouses ) ) : ?>
                        <?php foreach ( $warehouses as $index => $row ) : ?>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-2"><?php echo $index + 1; ?></td>
                                <td class="px-6 py-2 font-medium text-gray-900 whitespace-nowrap">
                                    <?php echo esc_html( $row->warehouse_name ); ?>
                                    <?php if ( $row->warehouse_type === 'system' ) : ?>
                                        <span class="ml-2 bg-blue-100 text-blue-800 text-[10px] font-semibold px-2 py-0.5 rounded uppercase">Main</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-2"><?php echo esc_html( $row->mobile ); ?></td>
                                <td class="px-6 py-2"><?php echo esc_html( $row->email ); ?></td>
                                <td class="px-6 py-2"><?php echo esc_html( $row->address ); ?></td>
                                <td class="px-6 py-2">
                                     <?php if ( $row->status == 1 ): ?>
                                        <span class="bg-green-100 text-green-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">Active</span>
                                     <?php else: ?>
                                        <span class="bg-red-100 text-red-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">Inactive</span>
                                     <?php endif; ?>
                                </td>
                                <td class="px-6 py-2 text-right">
                                    <a href="<?php echo esc_url( add_query_arg( [ 'view' => 'warehouse', 'action' => 'edit', 'id' => $row->id ] ) ); ?>" class="font-medium text-blue-600 hover:underline mr-3">Edit</a>
                                    <?php if ( $row->warehouse_type !== 'system' ) : ?>
                                        <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'view' => 'warehouse', 'action' => 'delete', 'id' => $row->id ] ), 'delete_warehouse_' . $row->id ) ); ?>" 
                                           onclick="return confirm('Are you sure you want to delete this warehouse?');" 
                                           class="font-medium text-red-600 hover:underline">Delete</a>
                                    <?php else : ?>
                                        <span class="text-gray-400 cursor-not-allowed" title="System warehouse cannot be deleted">Delete</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr class="bg-white border-b">
                            <td colspan="7" class="px-6 py-4 text-center">No warehouses found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <script>
    jQuery(document).ready(function($) {
        // Client-side Search
        $('#searchInput').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $("#warehouseTable tbody tr").filter(function() {
                // Check if row has data (skip "No warehouses found" if it somehow exists with data class, but here we just toggle)
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
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
            
            // Toggle header
            $('#warehouseTable thead tr th').eq(column).toggle(isChecked);
            // Toggle cells
            $('#warehouseTable tbody tr').each(function() {
                $(this).find('td').eq(column).toggle(isChecked);
            });
        });

        // Helper to get visible table data for export
        function getTableData() {
            const data = [];
            const headers = [];
            
            $('#warehouseTable thead tr th').each(function(index) {
                if($(this).is(':visible') && index < 6) { // Exclude Actions (last col is 6)
                    headers.push($(this).text().trim());
                }
            });
            data.push(headers);
            
            $('#warehouseTable tbody tr').each(function() {
                if($(this).is(':visible')){
                    const row = [];
                    $(this).find('td').each(function(index) {
                        if($(this).is(':visible') && index < 6) {
                            let text = $(this).text().trim();
                            // Clean up text
                            text = text.replace(/\s+/g, ' ').trim();
                            row.push(text);
                        }
                    });
                    if(row.length > 0) {
                        data.push(row);
                    }
                }
            });
            
            return data;
        }

        // Print functionality
        $('#printBtn').on('click', function() {
            const printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write('<html><head><title>Warehouse List</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: Arial, sans-serif; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 20px; }');
            printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
            printWindow.document.write('th { background-color: #f3f4f6; font-weight: bold; }');
            printWindow.document.write('h1 { text-align: center; color: #333; }');
            printWindow.document.write('</style></head><body>');
            printWindow.document.write('<h1>Warehouse List</h1>');
            
            const tableData = getTableData();
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
            printWindow.print();
        });

        // PDF Export
        $('#pdfBtn').on('click', function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            doc.setFontSize(18);
            doc.text('Warehouse List', 14, 22);
            
            const tableData = getTableData();
            const headers = tableData[0];
            const rows = tableData.slice(1);
            
            doc.autoTable({
                head: [headers],
                body: rows,
                startY: 30,
                theme: 'grid',
                styles: { fontSize: 10 },
                headStyles: { fillColor: [79, 70, 229] } // Indigo-600 match
            });
            
            doc.save('warehouse-list.pdf');
        });

        // Excel Export
        $('#excelBtn').on('click', function() {
            const tableData = getTableData();
            const ws = XLSX.utils.aoa_to_sheet(tableData);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Warehouses');
            XLSX.writeFile(wb, 'warehouse-list.xlsx');
        });

        // CSV Export
        $('#csvBtn').on('click', function() {
            const tableData = getTableData();
            let csv = '';
            
            tableData.forEach(function(row) {
                csv += row.map(function(cell) {
                    return '"' + cell.replace(/"/g, '""') + '"';
                }).join(',') + '\n';
            });
            
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'warehouse-list.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });

        // Clear message from URL after display to prevent it staying on refresh
        const currentUrl = new URL(window.location.href);
        if (currentUrl.searchParams.has('message')) {
            currentUrl.searchParams.delete('message');
            window.history.replaceState({}, '', currentUrl.toString());
        }
    });
    </script>
    <?php
}
?>
