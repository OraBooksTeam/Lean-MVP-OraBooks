<?php
/**
 * Frontend Store Profile Configuration
 * File: templates/settings/store-profile.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// echo "Debug: store-profile.php loaded successfully.<br>";

global $wpdb;
$table_name = $wpdb->prefix . 'orabooks_db_store';

// Fetch currencies from currency table
$currency_table = $wpdb->prefix . 'orabooks_db_currency';
$currencies = $wpdb->get_results("SELECT id, currency_name, currency_code, symbol FROM $currency_table WHERE status = 1 ORDER BY currency_name ASC");

// Diagnostic: Check if table exists
if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
    echo "<div class='p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50' role='alert'><span class='font-medium'>Error!</span> Table $table_name does not exist. Please check plugin activation.</div>";
    return;
}


// Fetch existing store data (Assuming single store for now, or edit via ID)
$store_id = isset( $_GET['store_id'] ) ? intval( $_GET['store_id'] ) : 0;

// If no ID passed, check if any store exists and load the first one (for single store systems)
if ( ! $store_id ) {
    $existing = $wpdb->get_row( "SELECT * FROM $table_name ORDER BY id DESC LIMIT 1" );
    if ( $existing ) {
        $store        = $existing;
        $store_id     = $existing->id;
        $action_label = "Update Store";
    } else {
        $store        = null;
        $action_label = "Save Store";
    }
} else {
    $store        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id=%d", $store_id ) );
    $action_label = $store ? "Update Store" : "Save Store";
}

// Diagnostic
if (!$store) {
   echo "<div class='p-4 mb-4 text-sm text-blue-800 rounded-lg bg-blue-50' role='alert'><strong>Diagnostic:</strong> No store data returned from DB for table $table_name. (Result is null)</div>";
} else {
    // echo "<div class='p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50' role='alert'><strong>Diagnostic:</strong> Data found for store ID " . $store->id . "</div>";
    // echo "<pre style='display:none;'>"; var_dump($store); echo "</pre>";
}

// Auto-Generate Store Code if adding new
$auto_store_code = '';
if ( ! $store ) {
    $last_store = $wpdb->get_row( "SELECT store_code FROM $table_name ORDER BY id DESC LIMIT 1" );
    if ( $last_store && preg_match( '/ST-(\d+)/', $last_store->store_code, $matches ) ) {
        $next_num        = intval( $matches[1] ) + 1;
        $auto_store_code = 'ST-' . str_pad( $next_num, 6, '0', STR_PAD_LEFT );
    } else {
        $auto_store_code = 'ST-000001';
    }
}

// --- Form Submission Handler ---
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['save_store_nonce'] ) && wp_verify_nonce( $_POST['save_store_nonce'], 'save_store_action' ) ) {

    // Collect & Sanitize Data

    // Store Tab
    $store_code = isset( $_POST['store_code'] ) ? sanitize_text_field( $_POST['store_code'] ) : 'ST-' . time(); // Basic auto-gen if empty
    $store_name = sanitize_text_field( $_POST['store_name'] );

    // System Tab
    $timezone           = sanitize_text_field( $_POST['timezone'] );
    $date_format        = sanitize_text_field( $_POST['date_format'] );
    $time_format        = sanitize_text_field( $_POST['time_format'] );
    $currency_id        = intval( $_POST['currency_id'] );
    $currency_placement = sanitize_text_field( $_POST['currency_placement'] );
    $decimals           = intval( $_POST['decimals'] );
    $qty_decimals       = intval( $_POST['qty_decimals'] );
    $language           = sanitize_text_field( $_POST['language'] );
    $round_off          = isset( $_POST['round_off'] ) ? 1 : 0;

    // Sales Tab
    $default_account_id     = intval( $_POST['default_account_id'] );
    $default_sales_discount = floatval( $_POST['default_sales_discount'] );
    $sales_invoice_format   = sanitize_text_field( $_POST['sales_invoice_format'] );
    $pos_invoice_format     = sanitize_text_field( $_POST['pos_invoice_format'] );
    $show_mrp_pos           = isset( $_POST['show_mrp_pos'] ) ? 1 : 0;
    // $show_paid_change_pos = isset($_POST['show_paid_change_pos']) ? 1 : 0;
    $show_prev_balance   = isset( $_POST['show_prev_balance'] ) ? 1 : 0;
    $num_to_words_format = sanitize_text_field( $_POST['num_to_words_format'] );
    $invoice_terms       = isset( $_POST['invoice_terms'] ) ? json_encode( $_POST['invoice_terms'] ) : ''; // Radio buttons may need logic check
    $sales_description   = sanitize_textarea_field( $_POST['sales_description'] );

    // Prefixes Tab
    $prefixes = [
        'category_init'                => sanitize_text_field( $_POST['category_init'] ),
        'supplier_init'                => sanitize_text_field( $_POST['supplier_init'] ),
        'purchase_return_init'         => sanitize_text_field( $_POST['purchase_return_init'] ),
        'sales_init'                   => sanitize_text_field( $_POST['sales_init'] ),
        'expense_init'                 => sanitize_text_field( $_POST['expense_init'] ),
        'quotation_init'               => sanitize_text_field( $_POST['quotation_init'] ),
        'sales_payment_init'           => sanitize_text_field( $_POST['sales_payment_init'] ),
        'purchase_payment_init'        => sanitize_text_field( $_POST['purchase_payment_init'] ),
        'expense_payment_init'         => sanitize_text_field( $_POST['expense_payment_init'] ),
        'item_init'                    => sanitize_text_field( $_POST['item_init'] ),
        'purchase_init'                => sanitize_text_field( $_POST['purchase_init'] ),
        'customer_init'                => sanitize_text_field( $_POST['customer_init'] ),
        'sales_return_init'            => sanitize_text_field( $_POST['sales_return_init'] ),
        'accounts_init'                => sanitize_text_field( $_POST['accounts_init'] ),
        'money_transfer_init'          => sanitize_text_field( $_POST['money_transfer_init'] ),
        'sales_return_payment_init'    => sanitize_text_field( $_POST['sales_return_payment_init'] ),
        'purchase_return_payment_init' => sanitize_text_field( $_POST['purchase_return_payment_init'] ),
        'cust_advance_init'            => sanitize_text_field( $_POST['cust_advance_init'] ),
    ];

    // Build Data Array for Insert/Update
    $data = [
        'store_code'     => $store_code,
        'store_name'     => $store_name,
        'mobile'         => sanitize_text_field( $_POST['mobile'] ),
        'email'          => sanitize_email( $_POST['email'] ),
        'phone'          => sanitize_text_field( $_POST['phone'] ),
        'gst_no'         => sanitize_text_field( $_POST['gst_no'] ),
        // 'tax_number' => sanitize_text_field($_POST['tax_number']),
        // 'pan_no' => sanitize_text_field($_POST['pan_no']),
        'store_website'  => esc_url_raw( $_POST['store_website'] ),
        'show_signature' => isset( $_POST['show_signature'] ) ? 1 : 0,
        'bank_details'   => sanitize_textarea_field( $_POST['bank_details'] ),
        'country'        => sanitize_text_field( $_POST['country'] ),
        'state'          => sanitize_text_field( $_POST['state'] ),
        'city'           => sanitize_text_field( $_POST['city'] ),
        'postcode'       => sanitize_text_field( $_POST['postcode'] ),
        'address'        => sanitize_textarea_field( $_POST['address'] ),

        // System
        'timezone'           => $timezone,
        'date_format'        => $date_format,
        'time_format'        => $time_format,
        'currency_id'        => $currency_id,
        'currency_placement' => $currency_placement,
        'decimals'           => $decimals,
        'qty_decimals'       => $qty_decimals,
        'language_id'        => $language,
        'round_off'          => $round_off,

        // Sales
        'default_account_id'      => $default_account_id,
        'sales_discount'          => $default_sales_discount,
        'sales_invoice_format_id' => $sales_invoice_format,
        'pos_invoice_format_id'   => $pos_invoice_format,
        'mrp_column'              => $show_mrp_pos,
        // 'show_paid_change_pos' => $show_paid_change_pos,
        'previous_balance_bit'    => $show_prev_balance,
        'number_to_words'         => $num_to_words_format,
        't_and_c_status'          => $invoice_terms, // Logic TBD closer to requirements
        'invoice_terms'           => $sales_description,
    ];

    // Merge prefixes
    $data = array_merge( $data, $prefixes );

    // Handle File Uploads
    require_once( ABSPATH . 'wp-admin/includes/file.php' );

    if ( ! empty( $_FILES['store_logo']['name'] ) ) {
        $upload = wp_handle_upload( $_FILES['store_logo'], [ 'test_form' => false ] );
        if ( isset( $upload['url'] ) ) {
            $data['store_logo'] = esc_url_raw( $upload['url'] );
        }
    }
    if ( ! empty( $_FILES['signature_image']['name'] ) ) {
        $upload = wp_handle_upload( $_FILES['signature_image'], [ 'test_form' => false ] );
        if ( isset( $upload['url'] ) ) {
            $data['signature'] = esc_url_raw( $upload['url'] );
        }
    }

    if ( $store_id ) {
        // UPDATE
        // $data['updated_at'] = current_time('mysql');
        $updated = $wpdb->update( $table_name, $data, [ 'id' => $store_id ] );
        if ( $updated !== false ) {
            // Sync with Warehouse
            $warehouse_table = $wpdb->prefix . 'orabooks_db_warehouse';
            $warehouse_data = [
                'store_id'       => $store_id,
                'warehouse_name' => $store_name,
                'mobile'         => sanitize_text_field( $_POST['mobile'] ),
                'email'          => sanitize_email( $_POST['email'] ),
                'status'         => 1,
            ];

            $existing_system_warehouse = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $warehouse_table WHERE warehouse_type = 'system' AND store_id = %d", $store_id ) );

            if ( $existing_system_warehouse ) {
                $wpdb->update( $warehouse_table, $warehouse_data, [ 'id' => $existing_system_warehouse->id ] );
            } else {
                $warehouse_data['warehouse_type'] = 'system';
                $warehouse_data['created_date']   = current_time( 'mysql' );
                $wpdb->insert( $warehouse_table, $warehouse_data );
            }

            echo '<div class="relative bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                    <p>Store configuration updated successfully!</p>
                    <button type="button" class="absolute top-4 right-4 text-green-700 hover:text-green-900 focus:outline-none" onclick="this.parentElement.style.display=\'none\';">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                  </div>';
            // Refresh data
            $store = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id=%d", $store_id ) );
        } else {
            echo '<div class="relative bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                    <p>Failed to update store configuration.</p>
                    <button type="button" class="absolute top-4 right-4 text-red-700 hover:text-red-900 focus:outline-none" onclick="this.parentElement.style.display=\'none\';">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                  </div>';
        }
    } else {
        // INSERT
        $data['created_date'] = date( 'Y-m-d' );
        $data['created_time'] = current_time( 'mysql' );
        $inserted             = $wpdb->insert( $table_name, $data );
        if ( $inserted ) {
            $store_id     = $wpdb->insert_id;

            // Sync with Warehouse
            $warehouse_table = $wpdb->prefix . 'orabooks_db_warehouse';
            $warehouse_data = [
                'store_id'       => $store_id,
                'warehouse_name' => $store_name,
                'mobile'         => sanitize_text_field( $_POST['mobile'] ),
                'email'          => sanitize_email( $_POST['email'] ),
                'status'         => 1,
                'warehouse_type' => 'system',
                'created_date'   => current_time( 'mysql' ),
            ];
            $wpdb->insert( $warehouse_table, $warehouse_data );

            echo '<div class="relative bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                    <p>Store configuration saved successfully!</p>
                    <button type="button" class="absolute top-4 right-4 text-green-700 hover:text-green-900 focus:outline-none" onclick="this.parentElement.style.display=\'none\';">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                  </div>';
            $store_id     = $wpdb->insert_id;
            $store        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id=%d", $store_id ) );
            $action_label = "Update Store";
        } else {
            echo '<div class="relative bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                    <p>Failed to save store configuration.</p>
                    <button type="button" class="absolute top-4 right-4 text-red-700 hover:text-red-900 focus:outline-none" onclick="this.parentElement.style.display=\'none\';">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                  </div>';
        }
    }
}
?>

<div class="bg-white rounded-lg shadow-lg p-6">
    <div class="flex items-center justify-between border-b pb-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Store Configuration</h1>
        <div class="text-sm text-gray-500">Configure your store settings, system preferences, and sales options.</div>
    </div>

    <form method="post" enctype="multipart/form-data" id="store-config-form">
        <?php wp_nonce_field( 'save_store_action', 'save_store_nonce' ); ?>
        <input type="hidden" name="store_id" value="<?php echo esc_attr( $store_id ); ?>">

        <!-- TABS -->
        <div class="mb-6 border-b border-gray-200">
            <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="settingsParamsTabs" data-tabs-toggle="#settingsParamsTabContent" role="tablist" style="list-style: none; padding: 0; margin: 0;">
                <li class="mr-2" role="presentation" style="list-style: none;">
                    <button class="inline-block p-4 border-b-2 rounded-t-lg hover:text-blue-600 hover:border-blue-600 dark:hover:text-blue-500 text-blue-600 border-blue-600 transition-colors duration-200" id="store-tab" data-tabs-target="#store" type="button" role="tab" aria-controls="store" aria-selected="true">Store Profile</button>
                </li>
                <li class="mr-2" role="presentation" style="list-style: none;">
                    <button class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-blue-600 hover:border-blue-600 dark:hover:text-blue-500 transition-colors duration-200" id="system-tab" data-tabs-target="#system" type="button" role="tab" aria-controls="system" aria-selected="false">System</button>
                </li>
                <li class="mr-2" role="presentation" style="list-style: none;">
                    <button class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-blue-600 hover:border-blue-600 dark:hover:text-blue-500 transition-colors duration-200" id="sales-tab" data-tabs-target="#sales" type="button" role="tab" aria-controls="sales" aria-selected="false">Sales</button>
                </li>
                <li role="presentation" style="list-style: none;">
                    <button class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-blue-600 hover:border-blue-600 dark:hover:text-blue-500 transition-colors duration-200" id="prefixes-tab" data-tabs-target="#prefixes" type="button" role="tab" aria-controls="prefixes" aria-selected="false">Prefixes</button>
                </li>
            </ul>
        </div>

        <div id="settingsParamsTabContent">
            
            <!-- 1. STORE TAB -->
            <div class="" id="store" role="tabpanel" aria-labelledby="store-tab">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div class="space-y-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Store Code</label>
                            <input type="text" name="store_code" value="<?php echo esc_attr( $store->store_code ?? $auto_store_code ); ?>" readonly class="bg-gray-100 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full py-2 px-2.5">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Store Name <span class="text-red-500">*</span></label>
                            <input type="text" name="store_name" value="<?php echo esc_attr( $store->store_name ?? '' ); ?>" required class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full py-2 px-2.5">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Mobile</label>
                            <input type="text" name="mobile" value="<?php echo esc_attr( $store->mobile ?? '' ); ?>" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full py-2 px-2.5">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Email</label>
                            <input type="email" name="email" value="<?php echo esc_attr( $store->email ?? '' ); ?>" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full py-2 px-2.5">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Phone</label>
                            <input type="text" name="phone" value="<?php echo esc_attr( $store->phone ?? '' ); ?>" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full py-2 px-2.5">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Store Website</label>
                            <input type="url" name="store_website" value="<?php echo esc_attr( $store->store_website ?? '' ); ?>" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full py-2 px-2.5">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Bank Details</label>
                            <textarea name="bank_details" rows="3" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500"><?php echo esc_textarea( $store->bank_details ?? '' ); ?></textarea>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Address</label>
                            <textarea name="address" rows="3" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500"><?php echo esc_textarea( $store->address ?? '' ); ?></textarea>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">GST Number</label>
                            <input type="text" name="gst_no" value="<?php echo esc_attr( $store->gst_no ?? '' ); ?>" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full py-2 px-2.5">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Tax Number</label>
                            <input type="text" name="tax_number" value="<?php echo esc_attr( $store->tax_number ?? '' ); ?>" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full py-2 px-2.5">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">PAN Number</label>
                            <input type="text" name="pan_no" value="<?php echo esc_attr( $store->pan_no ?? '' ); ?>" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full py-2 px-2.5">
                        </div>
                        <div class="flex items-center mb-4">
                            <input id="show_signature" type="checkbox" name="show_signature" value="1" <?php checked( $store->show_signature ?? 0, 1 ); ?> class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2">
                            <label for="show_signature" class="ml-2 text-sm font-medium text-gray-900">Show Signature on Invoice</label>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Signature Image</label>
                            <?php if ( ! empty( $store->signature_image ) ): ?>
                                <img src="<?php echo esc_url( $store->signature_image ); ?>" class="max-h-20 mb-2 rounded border">
                            <?php endif; ?>
                            <input type="file" name="signature_image" accept="image/*" class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Country</label>
                            <select name="country" id="country" data-selected="<?php echo esc_attr( $store->country ?? '' ); ?>" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="">Loading...</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">State</label>
                            <select name="state" id="state" data-selected="<?php echo esc_attr( $store->state ?? '' ); ?>" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="">Select Country First</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">City</label>
                            <input type="text" name="city" value="<?php echo esc_attr( $store->city ?? '' ); ?>" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full py-2 px-2.5">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Postcode</label>
                            <input type="text" name="postcode" value="<?php echo esc_attr( $store->postcode ?? '' ); ?>" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full py-2 px-2.5">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Store Logo</label>
                            <?php if ( ! empty( $store->store_logo ) ): ?>
                                <img src="<?php echo esc_url( $store->store_logo ); ?>" class="max-h-20 mb-2 rounded border">
                            <?php endif; ?>
                            <input type="file" name="store_logo" accept="image/*" class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. SYSTEM TAB -->
            <div class="hidden" id="system" role="tabpanel" aria-labelledby="system-tab">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div class="space-y-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Timezone</label>
                            <select name="timezone" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="Asia/Dhaka" <?php selected( $store->timezone ?? '', 'Asia/Dhaka' ); ?>>Asia/Dhaka</option>
                                <option value="Asia/Kolkata" <?php selected( $store->timezone ?? '', 'Asia/Kolkata' ); ?>>Asia/Kolkata</option>
                                <option value="UTC" <?php selected( $store->timezone ?? '', 'UTC' ); ?>>UTC</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Date Format</label>
                            <select name="date_format" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="d-m-Y" <?php selected( $store->date_format ?? '', 'd-m-Y' ); ?>>dd-mm-yyyy</option>
                                <option value="Y-m-d" <?php selected( $store->date_format ?? '', 'Y-m-d' ); ?>>yyyy-mm-dd</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Time Format</label>
                            <select name="time_format" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="12" <?php selected( $store->time_format ?? '', '12' ); ?>>12 Hours</option>
                                <option value="24" <?php selected( $store->time_format ?? '', '24' ); ?>>24 Hours</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Currency</label>
                            <select name="currency_id" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <?php if ($currencies && count($currencies) > 0): ?>
                                    <?php foreach ($currencies as $currency): ?>
                                        <option value="<?php echo esc_attr($currency->id); ?>" <?php selected( $store->currency_id ?? 0, $currency->id ); ?>>
                                            <?php echo esc_html($currency->currency_code); ?> (<?php echo esc_html($currency->symbol); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">No currencies available</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <!-- Right Column -->
                    <div class="space-y-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Currency Position</label>
                            <select name="currency_placement" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="before" <?php selected( $store->currency_placement ?? '', 'before' ); ?>>Before Amount</option>
                                <option value="after" <?php selected( $store->currency_placement ?? '', 'after' ); ?>>After Amount</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Decimals (Price)</label>
                            <select name="decimals" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="2" <?php selected( $store->decimals ?? 2, 2 ); ?>>2</option>
                                <option value="4" <?php selected( $store->decimals ?? 0, 4 ); ?>>4</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Decimals (Qty)</label>
                            <select name="qty_decimals" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="2" <?php selected( $store->qty_decimals ?? 2, 2 ); ?>>2</option>
                                <option value="0" <?php selected( $store->qty_decimals ?? 0, 0 ); ?>>0</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Language</label>
                            <select name="language" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="en" <?php selected( $store->language ?? '', 'en' ); ?>>English</option>
                            </select>
                        </div>
                        <div class="flex items-center mb-4">
                            <input id="round_off" type="checkbox" name="round_off" value="1" <?php checked( $store->round_off ?? 0, 1 ); ?> class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2">
                            <label for="round_off" class="ml-2 text-sm font-medium text-gray-900">Enable Round Off</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. SALES TAB -->
            <div class="hidden" id="sales" role="tabpanel" aria-labelledby="sales-tab">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div class="space-y-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Default Account</label>
                            <select name="default_account_id" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="">Select Account</option>
                                <?php
                                $accounts = $wpdb->get_results( "SELECT id, account_name FROM {$wpdb->prefix}orabooks_ac_accounts WHERE status=1" );
                                foreach ( $accounts as $acc ) {
                                    echo '<option value="' . $acc->id . '" ' . selected( $store->default_account_id ?? 0, $acc->id, false ) . '>' . $acc->account_name . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Default Sales Discount (%)</label>
                            <input type="number" step="0.01" name="default_sales_discount" value="<?php echo esc_attr( $store->default_sales_discount ?? 0 ); ?>" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Sales Invoice Format</label>
                            <select name="sales_invoice_format" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="Standard" <?php selected( $store->sales_invoice_format ?? '', 'Standard' ); ?>>Standard</option>
                                <option value="GST" <?php selected( $store->sales_invoice_format ?? '', 'GST' ); ?>>Indian GST</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">POS Invoice Format</label>
                            <select name="pos_invoice_format" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="Standard" <?php selected( $store->pos_invoice_format ?? '', 'Standard' ); ?>>Standard</option>
                                <option value="Thermal" <?php selected( $store->pos_invoice_format ?? '', 'Thermal' ); ?>>Thermal</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Number to Words Format</label>
                            <select name="num_to_words_format" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="Indian" <?php selected( $store->num_to_words_format ?? '', 'Indian' ); ?>>Indian (Lakhs/Crores)</option>
                                <option value="International" <?php selected( $store->num_to_words_format ?? '', 'International' ); ?>>International (Millions)</option>
                            </select>
                        </div>
                    </div>
                    <!-- Right Column -->
                    <div class="space-y-4">
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                             <h4 class="font-medium text-gray-900 mb-2">POS Settings</h4>
                             <div class="flex items-center mb-2">
                                <input id="show_mrp_pos" type="checkbox" name="show_mrp_pos" value="1" <?php checked( $store->show_mrp_pos ?? 0, 1 ); ?> class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2">
                                <label for="show_mrp_pos" class="ml-2 text-sm font-medium text-gray-900">Show MRP on POS</label>
                             </div>
                             <div class="flex items-center mb-2">
                                <input id="show_paid_change_pos" type="checkbox" name="show_paid_change_pos" value="1" <?php checked( $store->show_paid_change_pos ?? 0, 1 ); ?> class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2">
                                <label for="show_paid_change_pos" class="ml-2 text-sm font-medium text-gray-900">Show Paid & Change Return</label>
                             </div>
                        </div>

                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                             <h4 class="font-medium text-gray-900 mb-2">Other Settings</h4>
                             <div class="flex items-center mb-2">
                                <input id="show_prev_balance" type="checkbox" name="show_prev_balance" value="1" <?php checked( $store->show_prev_balance ?? 0, 1 ); ?> class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2">
                                <label for="show_prev_balance" class="ml-2 text-sm font-medium text-gray-900">Show Previous Balance on Invoice</label>
                             </div>
                        </div>

                         <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                             <h4 class="font-medium text-gray-900 mb-2">Invoice Terms and Conditions</h4>
                             <div class="space-y-2">
                                 <div class="flex items-center">
                                    <input type="checkbox" name="invoice_terms[]" value="show_invoice" class="w-4 h-4 text-blue-600 bg-white border-gray-300 rounded focus:ring-blue-500">
                                    <label class="ml-2 text-sm text-gray-900">Show on Invoice</label>
                                 </div>
                                 <div class="flex items-center">
                                    <input type="checkbox" name="invoice_terms[]" value="hide_invoice" class="w-4 h-4 text-blue-600 bg-white border-gray-300 rounded focus:ring-blue-500">
                                    <label class="ml-2 text-sm text-gray-900">Hide on Invoice</label>
                                 </div>
                                 <div class="flex items-center">
                                    <input type="checkbox" name="invoice_terms[]" value="show_pos" class="w-4 h-4 text-blue-600 bg-white border-gray-300 rounded focus:ring-blue-500">
                                    <label class="ml-2 text-sm text-gray-900">Show on POS Invoice</label>
                                 </div>
                                 <div class="flex items-center">
                                    <input type="checkbox" name="invoice_terms[]" value="hide_pos" class="w-4 h-4 text-blue-600 bg-white border-gray-300 rounded focus:ring-blue-500">
                                    <label class="ml-2 text-sm text-gray-900">Hide on POS Invoice</label>
                                 </div>
                             </div>
                        </div>
                        
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Description (Terms)</label>
                            <textarea name="sales_description" rows="3" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500"><?php echo esc_textarea( $store->sales_description ?? '' ); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. PREFIXES TAB -->
            <div class="hidden" id="prefixes" role="tabpanel" aria-labelledby="prefixes-tab">
                 <p class="text-sm text-gray-500 mb-4">Set the initial prefix code for auto-generated numbers.</p>
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php
                    $fields = [
                        'category_init'                => 'Category',
                        'supplier_init'                => 'Supplier',
                        'purchase_return_init'         => 'Purchase Return',
                        'sales_init'                   => 'Sales',
                        'expense_init'                 => 'Expense',
                        'quotation_init'               => 'Quotation',
                        'sales_payment_init'           => 'Sales Payment',
                        'purchase_payment_init'        => 'Purchase Payment',
                        'expense_payment_init'         => 'Expense Payment',
                        'item_init'                    => 'Item',
                        'purchase_init'                => 'Purchase',
                        'customer_init'                => 'Customer',
                        'sales_return_init'            => 'Sales Return',
                        'accounts_init'                => 'Accounts',
                        'money_transfer_init'          => 'Money Transfer',
                        'sales_return_payment_init'    => 'Sales Return Payment',
                        'purchase_return_payment_init' => 'Purchase Return Payment',
                        'cust_advance_init'            => 'Cust Advance Payments'
                    ];

                    // Split into two columns array for display logic if needed broadly, 
                    // but here we can just loop and let grid handle it
                    
                    foreach ( $fields as $key => $label ) {
                        echo '<div>';
                        echo '<label class="block mb-2 text-sm font-medium text-gray-900">' . $label . '</label>';
                        echo '<input type="text" name="' . $key . '" value="' . esc_attr( $store->$key ?? '' ) . '" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full py-2 px-2.5">';
                        echo '</div>';
                    }
                    ?>
                 </div>
            </div>
            
        </div>

        <div class="mt-8 pt-5 border-t border-gray-200">
             <button type="submit" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center mr-2 mb-2"><?php echo $action_label; ?></button>
        </div>

    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab functionality
        const tabLinks = document.querySelectorAll('[data-tabs-target]');
        const tabContents = document.querySelectorAll('[role="tabpanel"]');

        tabLinks.forEach(link => {
            link.addEventListener('click', () => {
                const target = document.querySelector(link.dataset.tabsTarget);

                // Hide all contents
                tabContents.forEach(content => {
                    content.classList.add('hidden');
                });
                // Remove active class from all tabs
                tabLinks.forEach(tab => {
                    tab.classList.remove('text-blue-600', 'border-blue-600');
                    tab.classList.add('hover:text-blue-600', 'hover:border-blue-600', 'border-transparent');
                    tab.setAttribute('aria-selected', 'false');
                });

                // Show target content
                target.classList.remove('hidden');
                // Add active class to clicked tab
                link.classList.remove('hover:text-blue-600', 'hover:border-blue-600', 'border-transparent');
                link.classList.add('text-blue-600', 'border-blue-600');
                link.setAttribute('aria-selected', 'true');
            });
        });

        // Initialize tabs (activate first one)
        if(tabLinks.length > 0) {
            tabLinks[0].click();
        }

        // Country & State Logic
        const countrySelect = document.getElementById('country');
        const stateSelect   = document.getElementById('state');
        const selectedCountryName = countrySelect.dataset.selected;
        const selectedStateName   = stateSelect.dataset.selected;
        
        // 1. Load Countries
        fetch('https://restcountries.com/v3.1/all?fields=name')
            .then(res => res.json())
            .then(data => {
                const sorted = data.sort((a,b)=>a.name.common.localeCompare(b.name.common));
                countrySelect.innerHTML = '<option value="">Select Country</option>';
                sorted.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.name.common;
                    opt.textContent = c.name.common;
                    if (c.name.common === selectedCountryName) {
                        opt.selected = true;
                    }
                    countrySelect.appendChild(opt);
                });
                
                // If pre-selected, trigger state load
                if (selectedCountryName) {
                    loadStates(selectedCountryName, selectedStateName);
                }
            })
            .catch(err => console.error('Error loading countries:', err));
            
        // 2. Change Listener
        countrySelect.addEventListener('change', function() {
            loadStates(this.value, '');
        });
        
        function loadStates(country, selectedState) {
            if (!country) {
                stateSelect.innerHTML = '<option value="">Select Country First</option>';
                return;
            }
            
            stateSelect.innerHTML = '<option>Loading...</option>';
            
            fetch('https://countriesnow.space/api/v0.1/countries/states', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ country: country })
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.data && data.data.states) {
                    stateSelect.innerHTML = '<option value="">Select State</option>';
                    data.data.states.forEach(st => {
                        const opt = document.createElement('option');
                        opt.value = st.name;
                        opt.textContent = st.name;
                        if (st.name === selectedState) {
                            opt.selected = true;
                        }
                        stateSelect.appendChild(opt);
                    });
                } else {
                    stateSelect.innerHTML = '<option value="">No states found</option>';
                }
            })
            .catch(err => {
                console.error('Error loading states:', err);
                stateSelect.innerHTML = '<option value="">Error</option>';
            });
        }
    });
</script>
