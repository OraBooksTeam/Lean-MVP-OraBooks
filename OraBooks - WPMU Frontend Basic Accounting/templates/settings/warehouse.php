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
    } elseif ( $_GET['message'] === 'deleted' ) {
        $msg = '<div class="relative p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg message-alert" role="alert">
                    <i class="fa-solid fa-check-circle mr-2"></i>Warehouse deleted successfully.
                    <button type="button" class="absolute top-4 right-4 text-green-700 hover:text-green-900 focus:outline-none" onclick="this.parentElement.style.display=\'none\';">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>';
    } elseif ( $_GET['message'] === 'delete_failed' ) {
        $msg = '<div class="relative p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg message-alert" role="alert">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i>Failed to delete warehouse.
                    <button type="button" class="absolute top-4 right-4 text-red-700 hover:text-red-900 focus:outline-none" onclick="this.parentElement.style.display=\'none\';">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>';
    } elseif ( $_GET['message'] === 'security_failed' ) {
        $msg = '<div class="relative p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg message-alert" role="alert">
                    <i class="fa-solid fa-shield-halved mr-2"></i>Security check failed.
                    <button type="button" class="absolute top-4 right-4 text-red-700 hover:text-red-900 focus:outline-none" onclick="this.parentElement.style.display=\'none\';">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>';
    }
}

// Handle error message transient set by OBN_Warehouse
$error_msg = get_transient( 'obn_warehouse_error_' . get_current_user_id() );
if ( $error_msg ) {
    $msg = '<div class="relative p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg message-alert" role="alert">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i>' . esc_html( $error_msg ) . '
                <button type="button" class="absolute top-4 right-4 text-red-700 hover:text-red-900 focus:outline-none" onclick="this.parentElement.style.display=\'none\';">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>';
    delete_transient( 'obn_warehouse_error_' . get_current_user_id() );
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
    <div class="p-6">
        <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center mr-4 shadow-sm">
                    <i class="fa-solid fa-warehouse text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800"><?php echo esc_html( $form_title ); ?></h1>
                    <p class="text-sm text-gray-500 mt-1">Manage warehouse information</p>
                </div>
            </div>
            <a href="<?php echo esc_url( add_query_arg( [ 'view' => 'warehouse', 'action' => 'list' ] ) ); ?>"
                class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-xl hover:from-indigo-700 hover:to-violet-700 transition-all font-bold text-xs uppercase tracking-widest shadow-lg shadow-indigo-200 hover:shadow-indigo-300 active:scale-95">
                <i class="fa-solid fa-arrow-left-long mr-2"></i> Back to List
            </a>
        </div>

        <?php echo $msg; ?>

        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
            <div class="p-6">
                <form method="post" class="space-y-6">
                    <?php wp_nonce_field( 'save_warehouse_action', 'save_warehouse_nonce' ); ?>
                    <input type="hidden" name="warehouse_id" value="<?php echo esc_attr( $edit_data->id ?? 0 ); ?>">

                    <?php if ( $action === 'edit' ) : ?>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Warehouse Name -->
                        <div class="lg:col-span-2">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Warehouse Name <span class="text-red-500">*</span></label>
                            <input type="text" name="warehouse_name" value="<?php echo esc_attr( $edit_data->warehouse_name ?? '' ); ?>" required
                                class="w-full bg-white text-gray-700 border border-gray-300 rounded-lg py-2.5 px-4 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 placeholder-gray-400"
                                placeholder="Enter warehouse name">
                        </div>

                        <!-- Mobile -->
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Mobile</label>
                            <input type="text" name="mobile" value="<?php echo esc_attr( $edit_data->mobile ?? '' ); ?>"
                                class="w-full bg-white text-gray-700 border border-gray-300 rounded-lg py-2.5 px-4 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 placeholder-gray-400"
                                placeholder="Enter mobile number">
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                            <input type="email" name="email" value="<?php echo esc_attr( $edit_data->email ?? '' ); ?>"
                                class="w-full bg-white text-gray-700 border border-gray-300 rounded-lg py-2.5 px-4 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 placeholder-gray-400"
                                placeholder="Enter email address">
                        </div>

                        <!-- Address -->
                        <div class="lg:col-span-2">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Address</label>
                            <textarea name="address" rows="3"
                                class="w-full bg-white text-gray-700 border border-gray-300 rounded-lg py-2.5 px-4 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 placeholder-gray-400"
                                placeholder="Enter warehouse address"><?php echo esc_textarea( $edit_data->address ?? '' ); ?></textarea>
                        </div>
                    </div>
                    <?php else : ?>
                    <div id="warehouse-rows-container" class="space-y-4">
                        <div class="warehouse-row grid grid-cols-1 md:grid-cols-12 gap-4 items-start bg-gray-50 p-4 rounded-xl border border-gray-200 relative group transition-all hover:shadow-md">
                            <div class="md:col-span-3">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Warehouse Name <span class="text-red-500">*</span></label>
                                <input type="text" name="warehouse_name[]" required class="w-full bg-white border border-gray-300 rounded-lg py-2 px-3 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm" placeholder="Name">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Mobile</label>
                                <input type="text" name="mobile[]" class="w-full bg-white border border-gray-300 rounded-lg py-2 px-3 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm" placeholder="Mobile">
                            </div>
                            <div class="md:col-span-3">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                                <input type="email" name="email[]" class="w-full bg-white border border-gray-300 rounded-lg py-2 px-3 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm" placeholder="Email">
                            </div>
                            <div class="md:col-span-3">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Address</label>
                                <textarea name="address[]" rows="1" class="w-full bg-white border border-gray-300 rounded-lg py-2 px-3 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm" placeholder="Address"></textarea>
                            </div>
                            <div class="md:col-span-1 flex justify-end items-end h-full pb-1">
                                <button type="button" class="remove-row-btn text-red-500 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 transition-colors hidden" title="Remove Row">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <button type="button" id="add-warehouse-row" class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800 hover:bg-blue-50 px-3 py-1.5 rounded-lg transition-colors">
                            <i class="fa-solid fa-plus-circle mr-1"></i> Add Another Row
                        </button>
                    </div>
                    <?php endif; ?>

                    <div class="flex justify-end pt-4 border-t border-gray-100">
                        <button type="submit" class="inline-flex items-center px-6 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-xl hover:from-indigo-700 hover:to-violet-700 transition-all font-bold text-xs uppercase tracking-widest shadow-lg shadow-indigo-200 hover:shadow-indigo-300 active:scale-95">
                            <i class="fa-solid fa-save mr-2"></i> <?php echo esc_html( $btn_text ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
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
                <input type="search" id="warehouseSearchInput" class="block w-full pl-8 pr-3 py-2 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500" placeholder="Search warehouses...">
            </div>
            
            <!-- Export & Column Buttons -->
            <div class="export-toolbar flex gap-2 flex-wrap">
                <button id="warehousePrintBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" title="Print">
                    <i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
                </button>
                <button id="warehousePdfBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" title="Export to PDF">
                    <i class="fa-solid fa-file-pdf mr-1 text-red-600"></i> <span class="hidden sm:inline">PDF</span>
                </button>
                <button id="warehouseExcelBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" title="Export to Excel">
                    <i class="fa-solid fa-file-excel mr-1 text-green-600"></i> <span class="hidden sm:inline">Excel</span>
                </button>
                <button id="warehouseCsvBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" title="Export to CSV">
                    <i class="fa-solid fa-file-csv mr-1 text-blue-600"></i> <span class="hidden sm:inline">CSV</span>
                </button>
                
                <!-- Column Visibility Dropdown -->
                <div class="relative">
                    <button id="warehouseColumnToggleBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors" title="Toggle Columns">
                        <i class="fa-solid fa-columns mr-1"></i> Columns
                    </button>
                    <div id="warehouseColumnDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                        <div class="p-3 space-y-2">
                            <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                                <input type="checkbox" class="warehouse-column-toggle mr-2" data-column="0" checked> #
                            </label>
                            <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                                <input type="checkbox" class="warehouse-column-toggle mr-2" data-column="1" checked> Warehouse Name
                            </label>
                            <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                                <input type="checkbox" class="warehouse-column-toggle mr-2" data-column="2" checked> Mobile
                            </label>
                            <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                                <input type="checkbox" class="warehouse-column-toggle mr-2" data-column="3" checked> Email
                            </label>
                            <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                                <input type="checkbox" class="warehouse-column-toggle mr-2" data-column="4" checked> Address
                            </label>
                            <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                                <input type="checkbox" class="warehouse-column-toggle mr-2" data-column="5" checked> Status
                            </label>
                            <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                                <input type="checkbox" class="warehouse-column-toggle mr-2" data-column="6" checked> Actions
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="relative overflow-x-auto">
            <table id="warehouseTable" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 border-b border-gray-200">
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
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" class="sr-only peer warehouse-toggle-status" data-id="<?php echo $row->id; ?>" data-status="<?php echo $row->status; ?>" <?php checked( $row->status, 1 ); ?>>
                                        <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
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
    <?php
}
?>

    <script>
    jQuery(document).ready(function($) {
        // Dynamic Rows for Add Warehouse
        $('#add-warehouse-row').on('click', function() {
            var container = $('#warehouse-rows-container');
            var firstRow = container.find('.warehouse-row').first();
            var newRow = firstRow.clone();
            
            // Clear inputs
            newRow.find('input').val('');
            newRow.find('textarea').val('');
            
            container.append(newRow);
            updateRemoveButtons();
        });
        
        $(document).on('click', '.remove-row-btn', function() {
            $(this).closest('.warehouse-row').remove();
            updateRemoveButtons();
        });
        
        function updateRemoveButtons() {
            var rows = $('.warehouse-row');
            if (rows.length > 1) {
                rows.find('.remove-row-btn').removeClass('hidden');
            } else {
                rows.find('.remove-row-btn').addClass('hidden');
            }
        }

        // Client-side Search
        $('#warehouseSearchInput').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $("#warehouseTable tbody tr").filter(function() {
                // Check if row has data (skip "No warehouses found" if it somehow exists with data class, but here we just toggle)
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        // Column visibility toggle
        $('#warehouseColumnToggleBtn').on('click', function(e) {
            e.stopPropagation();
            $('#warehouseColumnDropdown').toggleClass('hidden');
        });
        
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#warehouseColumnToggleBtn, #warehouseColumnDropdown').length) {
                $('#warehouseColumnDropdown').addClass('hidden');
            }
        });
        
        $('.warehouse-column-toggle').on('change', function() {
            const column = $(this).data('column');
            const isChecked = $(this).is(':checked');
            
            // Toggle header
            $('#warehouseTable thead tr th').eq(column).toggle(isChecked);
            // Toggle cells
            $('#warehouseTable tbody tr').each(function() {
                $(this).find('td').eq(column).toggle(isChecked);
            });
        });

        // Toggle warehouse status
        $(document).on('change', '.warehouse-toggle-status', function() {
            let cb = $(this);
            let id = cb.data('id');
            let status = cb.data('status');
            
            $.ajax({
                url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
                type: 'POST',
                data: {
                    action: 'obn_toggle_warehouse_status',
                    id: id,
                    status: status,
                    security: '<?php echo wp_create_nonce( 'frontend_ajax_nonce' ); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        cb.data('status', response.data.new_status);
                    } else {
                        alert('Failed to update warehouse status: ' + (response.data.message || response.data || 'Unknown error'));
                        cb.prop('checked', !cb.prop('checked'));
                    }
                },
                error: function() {
                    alert('Request failed. Please check connection.');
                    cb.prop('checked', !cb.prop('checked'));
                }
            });
        });

        // Helper to get visible table data for export
        function getWarehouseTableData() {
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
        $('#warehousePrintBtn').on('click', function() {
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
            
            const tableData = getWarehouseTableData();
            printWindow.document.write('<table>');
            tableData.forEach(function(row, index) {
                printWindow.document.write('<tr>');
                row.forEach(function(cell) {
                    const tag = index === 0 ? 'th' : 'td';
                    printWindow.document.write('<' + tag + '>' + cell + '</' + tag + '>');
                });
                printWindow.document.write('</tr>');
            });
            printWindow.document.write('<table></body></html>');
            printWindow.document.close();
            printWindow.print();
        });

        // PDF Export
        $('#warehousePdfBtn').on('click', function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            doc.setFontSize(18);
            doc.text('Warehouse List', 14, 22);
            
            const tableData = getWarehouseTableData();
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
        $('#warehouseExcelBtn').on('click', function() {
            const tableData = getWarehouseTableData();
            const ws = XLSX.utils.aoa_to_sheet(tableData);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Warehouses');
            XLSX.writeFile(wb, 'warehouse-list.xlsx');
        });

        // CSV Export
        $('#warehouseCsvBtn').on('click', function() {
            const tableData = getWarehouseTableData();
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
