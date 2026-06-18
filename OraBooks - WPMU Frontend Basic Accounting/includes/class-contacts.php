<?php
if (!defined('ABSPATH')) {
    exit;
}

class OBN_Accounting_Contacts
{

    public static function init()
    {
        // Customers
        add_action('wp_ajax_frontend_save_customer', array(__CLASS__, 'handle_save_customer'));
        add_action('wp_ajax_nopriv_frontend_save_customer', array(__CLASS__, 'handle_save_customer'));
        add_action('wp_ajax_frontend_delete_customer', array(__CLASS__, 'handle_delete_customer'));
        add_action('wp_ajax_nopriv_frontend_delete_customer', array(__CLASS__, 'handle_delete_customer'));
        add_action('wp_ajax_frontend_update_customer_status', array(__CLASS__, 'handle_update_customer_status'));
        add_action('wp_ajax_nopriv_frontend_update_customer_status', array(__CLASS__, 'handle_update_customer_status'));

        // Customer Import
        add_action('wp_ajax_frontend_import_customers', array(__CLASS__, 'handle_import_customers'));
        add_action('wp_ajax_frontend_download_customer_template', array(__CLASS__, 'handle_download_customer_template'));

        // Suppliers
        add_action('wp_ajax_frontend_save_supplier', array(__CLASS__, 'handle_save_supplier'));
        add_action('wp_ajax_nopriv_frontend_save_supplier', array(__CLASS__, 'handle_save_supplier'));
        add_action('wp_ajax_frontend_delete_supplier', array(__CLASS__, 'handle_delete_supplier'));
        add_action('wp_ajax_nopriv_frontend_delete_supplier', array(__CLASS__, 'handle_delete_supplier'));
        add_action('wp_ajax_frontend_update_supplier_status', array(__CLASS__, 'handle_update_supplier_status'));
        add_action('wp_ajax_nopriv_frontend_update_supplier_status', array(__CLASS__, 'handle_update_supplier_status'));

        // Supplier Import
        add_action('wp_ajax_frontend_import_suppliers', array(__CLASS__, 'handle_import_suppliers'));
        add_action('wp_ajax_frontend_download_supplier_template', array(__CLASS__, 'handle_download_supplier_template'));

        // Customer Payments
        add_action('wp_ajax_frontend_save_customer_pay', array(__CLASS__, 'handle_save_customer_pay'));
        add_action('wp_ajax_frontend_delete_customer_pay', array(__CLASS__, 'handle_delete_customer_pay'));
        add_action('wp_ajax_frontend_update_customer_pay_status', array(__CLASS__, 'handle_update_customer_pay_status'));
        add_action('wp_ajax_frontend_get_customer_invoices', array(__CLASS__, 'handle_get_customer_invoices'));

        // Supplier Payments
        add_action('wp_ajax_frontend_save_supplier_pay', array(__CLASS__, 'handle_save_supplier_pay'));
        add_action('wp_ajax_frontend_delete_supplier_pay', array(__CLASS__, 'handle_delete_supplier_pay'));
        add_action('wp_ajax_frontend_update_supplier_pay_status', array(__CLASS__, 'handle_update_supplier_pay_status'));
        add_action('wp_ajax_frontend_get_supplier_invoices', array(__CLASS__, 'handle_get_supplier_invoices'));
    }

    /* ========================= CUSTOMERS ========================= */

    public static function handle_save_customer()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_customers';

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = sanitize_text_field($_POST['customer_name']);

        if (empty($name)) {
            wp_send_json_error(array('message' => 'Customer Name is required'));
        }

        $store_id = isset($_POST['store_id']) ? intval($_POST['store_id']) : 1;
        $count_id = 0;
        $customer_code = '';

        // Prevent Duplicate: Check if customer_code already exists
        $req_customer_code = sanitize_text_field($_POST['customer_code'] ?? '');
        if ($id == 0 && !empty($req_customer_code)) {
            $existing_customer = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE customer_code = %s", $req_customer_code));
            if ($existing_customer) {
                wp_send_json_error(array('message' => 'Duplicate Entry: Customer code ' . $req_customer_code . ' already exists.'));
            }
        }

        if ($id == 0) {
            // Atomic generation for new record
            $count_id = $wpdb->get_var($wpdb->prepare("SELECT MAX(count_id) FROM $table WHERE store_id = %d", $store_id));
            $count_id = ($count_id) ? intval($count_id) + 1 : 1;

            // Get prefix from store
            $prefix = $wpdb->get_var($wpdb->prepare("SELECT customer_init FROM {$wpdb->prefix}orabooks_db_store WHERE id = %d", $store_id));
            if (!$prefix)
                $prefix = 'CUS-';
            $customer_code = $prefix . str_pad($count_id, 6, '0', STR_PAD_LEFT);
        }

        $data = array(
            'store_id' => $store_id,
            'customer_name' => $name,
            'mobile' => sanitize_text_field($_POST['mobile']),
            'phone' => sanitize_text_field($_POST['phone']),
            'email' => sanitize_email($_POST['email']),
            'gstin' => sanitize_text_field($_POST['gstin']),
            'tax_number' => sanitize_text_field($_POST['tax_number']),
            'credit_limit' => floatval($_POST['credit_limit']),
            'opening_balance' => floatval($_POST['opening_balance']),
            'country_id' => sanitize_text_field($_POST['country_id']),
            'state_id' => sanitize_text_field($_POST['state_id']),
            'city' => sanitize_text_field($_POST['city']),
            'postcode' => sanitize_text_field($_POST['postcode']),
            'address' => sanitize_textarea_field($_POST['address']),
            'location_link' => esc_url_raw($_POST['location_link']),

            // Shipping
            'ship_country_id' => sanitize_text_field($_POST['ship_country_id']),
            'ship_state_id' => sanitize_text_field($_POST['ship_state_id']),
            'ship_city' => sanitize_text_field($_POST['ship_city']),
            'ship_postcode' => sanitize_text_field($_POST['ship_postcode']),
            'ship_address' => sanitize_textarea_field($_POST['ship_address']),

            // Advanced
            'price_level_type' => sanitize_text_field($_POST['price_level_type']),
            'price_level' => floatval($_POST['price_level']),

            // Defaults (might be overwritten by plugin-wide settings if integrated further)
            'created_date' => current_time('Y-m-d'),
            'created_time' => current_time('mysql'),
            'status' => 1
        );

        if ($id == 0) {
            $data['count_id'] = $count_id;
            $data['customer_code'] = $customer_code;
        }

        // Handle File Upload
        if (!empty($_FILES['attachment_1']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $upload = wp_handle_upload($_FILES['attachment_1'], array('test_form' => false));
            if (isset($upload['url'])) {
                $data['attachment_1'] = esc_url_raw($upload['url']);
            }
        }

        if ($id > 0) {
            // Update
            unset($data['created_date']);
            unset($data['created_time']);
            unset($data['status']); // Don't reset status on edit

            $wpdb->update($table, $data, array('id' => $id));

            self::create_customer_payment_journal_entry($id);

            wp_send_json_success(array('message' => 'Payment updated'));
        } else {
            // Add system info for new customer
            $data['system_ip'] = sanitize_text_field(getHostByName(getHostName()));
            $data['system_name'] = sanitize_text_field(gethostname());
            $data['created_by'] = get_current_user_id();

            // Insert
            $inserted = $wpdb->insert($table, $data);
            if ($inserted) {
                $new_customer_id = $wpdb->insert_id;
                wp_send_json_success(array(
                    'message' => 'Customer added',
                    'customer_id' => $new_customer_id,
                    'customer_name' => $name
                ));
            } else {
                wp_send_json_error(array('message' => 'Database error'));
            }
        }
    }

    public static function handle_delete_customer()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'orabooks_db_customers', array('id' => intval($_POST['id'])));
        wp_send_json_success();
    }

    public static function handle_update_customer_status()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'orabooks_db_customers',
            array('status' => intval($_POST['status'])),
            array('id' => intval($_POST['id']))
        );
        wp_send_json_success();
    }

    /* ========================= CUSTOMER IMPORT ========================= */

    public static function handle_download_customer_template()
    {
        if (!orabooks_can_access_accounting()) {
            wp_die('Access denied.');
        }

        $headers = array(
            'Customer Name',
            'Mobile',
            'Email',
            'Phone',
            'GST Number',
            'TAX Number',
            'Previous Due',
            'Credit Limit',
            'Country Name',
            'State Name',
            'Postcode',
            'Address',
            'Location Link'
        );

        $sample_data = array(
            'John Doe',
            '1234567890',
            'john@example.com',
            '0987654321',
            'GST12345',
            'TAX54321',
            '500.00',
            '-1',
            'Bangladesh',
            'Dhaka',
            '1200',
            '123 Street, Dhaka',
            'https://maps.google.com/...'
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=customer_import_template.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);
        fputcsv($output, $sample_data);
        fclose($output);
        exit;
    }

    public static function handle_import_customers()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if (!orabooks_can_access_accounting()) {
            wp_send_json_error(array('message' => 'Access denied.'));
        }

        if (empty($_FILES['customer_csv']['tmp_name'])) {
            wp_send_json_error(array('message' => 'Please upload a CSV file.'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_customers';
        $store_table = $wpdb->prefix . 'orabooks_db_store';

        $store_id = 1; // Default
        $prefix = 'CUS-';
        $store = $wpdb->get_row("SELECT id, customer_init FROM $store_table LIMIT 1");
        if ($store) {
            $store_id = $store->id;
            if (!empty($store->customer_init))
                $prefix = $store->customer_init;
        }

        $file = $_FILES['customer_csv']['tmp_name'];
        $handle = fopen($file, 'r');

        if ($handle === false) {
            wp_send_json_error(array('message' => 'Failed to open file.'));
        }

        // Read header row
        $headers = fgetcsv($handle);

        $imported_count = 0;
        $error_count = 0;
        $errors = array();

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 1)
                continue;

            $name = sanitize_text_field($row[0]);
            $mobile = sanitize_text_field($row[1]);
            $email = sanitize_email($row[2]);
            $phone = sanitize_text_field($row[3]);
            $gstin = sanitize_text_field($row[4]);
            $tax_number = sanitize_text_field($row[5]);
            $previous_due = floatval($row[6]);
            $credit_limit = floatval($row[7]);
            $country = sanitize_text_field($row[8]);
            $state = sanitize_text_field($row[9]);
            $postcode = sanitize_text_field($row[10]);
            $address = sanitize_textarea_field($row[11]);
            $location_link = esc_url_raw($row[12]);

            if (empty($name)) {
                $error_count++;
                $errors[] = "Row " . ($imported_count + $error_count + 1) . ": Customer Name is required.";
                continue;
            }

            // Generate Code
            $last_count = $wpdb->get_var($wpdb->prepare("SELECT MAX(count_id) FROM $table WHERE store_id = %d", $store_id));
            $count_id = ($last_count) ? intval($last_count) + 1 : 1;
            $customer_code = $prefix . str_pad($count_id, 6, '0', STR_PAD_LEFT);

            $data = array(
                'store_id' => $store_id,
                'count_id' => $count_id,
                'customer_code' => $customer_code,
                'customer_name' => $name,
                'mobile' => $mobile,
                'email' => $email,
                'phone' => $phone,
                'gstin' => $gstin,
                'tax_number' => $tax_number,
                'opening_balance' => $previous_due,
                'credit_limit' => $credit_limit,
                'country_id' => $country,
                'state_id' => $state,
                'postcode' => $postcode,
                'address' => $address,
                'location_link' => $location_link,
                'created_date' => current_time('Y-m-d'),
                'created_time' => current_time('mysql'),
                'system_ip' => sanitize_text_field(getHostByName(getHostName())),
                'system_name' => sanitize_text_field(gethostname()),
                'created_by' => get_current_user_id(),
                'status' => 1
            );

            $inserted = $wpdb->insert($table, $data);
            if ($inserted) {
                $imported_count++;
            } else {
                $error_count++;
                $errors[] = "Row " . ($imported_count + $error_count) . ": Database error - " . $wpdb->last_error;
            }
        }

        fclose($handle);

        wp_send_json_success(array(
            'message' => "Import completed! $imported_count customers imported.",
            'imported' => $imported_count,
            'errors' => $errors
        ));
    }

    /* ========================= SUPPLIERS ========================= */

    public static function handle_save_supplier()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_suppliers';

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = sanitize_text_field($_POST['supplier_name']);

        if (empty($name)) {
            wp_send_json_error(array('message' => 'Supplier Name is required'));
        }

        $store_id = isset($_POST['store_id']) ? intval($_POST['store_id']) : 1;
        $count_id = 0;
        $supplier_code = '';

        // Prevent Duplicate: Check if supplier_code already exists
        $req_supplier_code = sanitize_text_field($_POST['supplier_code'] ?? '');
        if ($id == 0 && !empty($req_supplier_code)) {
            $existing_supplier = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE supplier_code = %s", $req_supplier_code));
            if ($existing_supplier) {
                wp_send_json_error(array('message' => 'Duplicate Entry: Supplier code ' . $req_supplier_code . ' already exists.'));
            }
        }

        if ($id == 0) {
            // Atomic generation for new record
            $count_id = $wpdb->get_var($wpdb->prepare("SELECT MAX(count_id) FROM $table WHERE store_id = %d", $store_id));
            $count_id = ($count_id) ? intval($count_id) + 1 : 1;

            // Get prefix from store
            $prefix = $wpdb->get_var($wpdb->prepare("SELECT supplier_init FROM {$wpdb->prefix}orabooks_db_store WHERE id = %d", $store_id));
            if (!$prefix)
                $prefix = 'SUP-';
            $supplier_code = $prefix . str_pad($count_id, 6, '0', STR_PAD_LEFT);
        }

        $data = array(
            'store_id' => $store_id,
            'supplier_name' => $name,
            'mobile' => sanitize_text_field($_POST['mobile']),
            'email' => sanitize_email($_POST['email']),
            'gstin' => sanitize_text_field($_POST['gst_number']),
            'tax_number' => sanitize_text_field($_POST['tax_number']),
            'opening_balance' => floatval($_POST['opening_balance']),
            'country_id' => sanitize_text_field($_POST['country']),
            'state_id' => sanitize_text_field($_POST['state']),
            'city' => sanitize_text_field($_POST['city']),
            'postcode' => sanitize_text_field($_POST['postcode']),
            'address' => sanitize_textarea_field($_POST['address']),
            // Defaults
            'system_ip' => sanitize_text_field(getHostByName(getHostName())),
            'system_name' => sanitize_text_field(gethostname()),
            'created_date' => current_time('Y-m-d'),
            'created_time' => current_time('mysql'),
            'created_by' => get_current_user_id(),
            'status' => 1
        );

        if ($id == 0) {
            $data['count_id'] = $count_id;
            $data['supplier_code'] = $supplier_code;
        }

        if ($id > 0) {
            // Update
            unset($data['created_date']);
            unset($data['created_time']);
            unset($data['status']);

            $wpdb->update($table, $data, array('id' => $id));
            wp_send_json_success(array(
                'message' => 'Supplier updated',
                'supplier_id' => $id,
                'supplier_name' => $name
            ));
        } else {
            // Insert
            $inserted = $wpdb->insert($table, $data);
            if ($inserted) {
                $new_id = $wpdb->insert_id;
                wp_send_json_success(array(
                    'message' => 'Supplier added',
                    'supplier_id' => $new_id,
                    'supplier_name' => $name
                ));
            } else {
                wp_send_json_error(array('message' => 'Database error'));
            }
        }
    }

    public static function handle_delete_supplier()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'orabooks_db_suppliers', array('id' => intval($_POST['id'])));
        wp_send_json_success();
    }

    public static function handle_update_supplier_status()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'orabooks_db_suppliers',
            array('status' => intval($_POST['status'])),
            array('id' => intval($_POST['id']))
        );
        wp_send_json_success();
    }

    /* ========================= SUPPLIER IMPORT ========================= */

    public static function handle_download_supplier_template()
    {
        if (!orabooks_can_access_accounting()) {
            wp_die('Access denied.');
        }

        $headers = array(
            'Supplier Name',
            'Mobile',
            'Email',
            'Phone',
            'GST Number',
            'TAX Number',
            'Country Name',
            'State Name',
            'Postcode',
            'Address',
            'Opening Balance'
        );

        $sample_data = array(
            'Acme Supplies',
            '1234567890',
            'sales@acme.com',
            '0987654321',
            'GST99988',
            'TAX77766',
            'Bangladesh',
            'Dhaka',
            '1200',
            '456 Industrial Ave, Dhaka',
            '1500.00'
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=supplier_import_template.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);
        fputcsv($output, $sample_data);
        fclose($output);
        exit;
    }

    public static function handle_import_suppliers()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if (!orabooks_can_access_accounting()) {
            wp_send_json_error(array('message' => 'Access denied.'));
        }

        if (empty($_FILES['supplier_csv']['tmp_name'])) {
            wp_send_json_error(array('message' => 'Please upload a CSV file.'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_suppliers';
        $store_table = $wpdb->prefix . 'orabooks_db_store';

        $store_id = 1;
        $prefix = 'SUP-';
        $store = $wpdb->get_row("SELECT id, supplier_init FROM $store_table LIMIT 1");
        if ($store) {
            $store_id = $store->id;
            if (!empty($store->supplier_init))
                $prefix = $store->supplier_init;
        }

        $file = $_FILES['supplier_csv']['tmp_name'];
        $handle = fopen($file, 'r');

        if ($handle === false) {
            wp_send_json_error(array('message' => 'Failed to open file.'));
        }

        // Read header row
        $headers = fgetcsv($handle);

        $imported_count = 0;
        $error_count = 0;
        $errors = array();

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 1)
                continue;

            $name = sanitize_text_field($row[0]);
            $mobile = sanitize_text_field($row[1]);
            $email = sanitize_email($row[2]);
            $phone = sanitize_text_field($row[3]);
            $gstin = sanitize_text_field($row[4]);
            $tax_number = sanitize_text_field($row[5]);
            $country = sanitize_text_field($row[6]);
            $state = sanitize_text_field($row[7]);
            $postcode = sanitize_text_field($row[8]);
            $address = sanitize_textarea_field($row[9]);
            $opening_bal = floatval($row[10]);

            if (empty($name)) {
                $error_count++;
                $errors[] = "Row " . ($imported_count + $error_count + 1) . ": Supplier Name is required.";
                continue;
            }

            // Generate Code
            $last_count = $wpdb->get_var($wpdb->prepare("SELECT MAX(count_id) FROM $table WHERE store_id = %d", $store_id));
            $count_id = ($last_count) ? intval($last_count) + 1 : 1;
            $supplier_code = $prefix . str_pad($count_id, 6, '0', STR_PAD_LEFT);

            $data = array(
                'store_id' => $store_id,
                'count_id' => $count_id,
                'supplier_code' => $supplier_code,
                'supplier_name' => $name,
                'mobile' => $mobile,
                'email' => $email,
                'phone' => $phone,
                'gstin' => $gstin,
                'tax_number' => $tax_number,
                'country_id' => $country,
                'state_id' => $state,
                'postcode' => $postcode,
                'address' => $address,
                'opening_balance' => $opening_bal,
                'created_date' => current_time('Y-m-d'),
                'created_time' => current_time('mysql'),
                'system_ip' => sanitize_text_field(getHostByName(getHostName())),
                'system_name' => sanitize_text_field(gethostname()),
                'created_by' => get_current_user_id(),
                'status' => 1
            );

            $inserted = $wpdb->insert($table, $data);
            if ($inserted) {
                $imported_count++;
            } else {
                $error_count++;
                $errors[] = "Row " . ($imported_count + $error_count) . ": Database error - " . $wpdb->last_error;
            }
        }

        fclose($handle);

        wp_send_json_success(array(
            'message' => "Import completed! $imported_count suppliers imported.",
            'imported' => $imported_count,
            'errors' => $errors
        ));
    }

    /* ========================= CUSTOMER PAYMENTS ========================= */

    public static function handle_save_customer_pay()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_customer_payments';
        $sales_table = $wpdb->prefix . 'orabooks_db_sales';

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $customer_id = intval($_POST['customer_id']);
        $payment_date = sanitize_text_field($_POST['payment_date']);
        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            if ($id > 0) {
                $existing_payment = $wpdb->get_row($wpdb->prepare("SELECT payment_date FROM $table WHERE id = %d", $id));
                if (!$existing_payment) {
                    wp_send_json_error('Payment not found');
                }
                $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(obn_current_org_id(), $existing_payment->payment_date);
                if (is_wp_error($modification_allowed)) {
                    wp_send_json_error($modification_allowed->get_error_message(), 409);
                }
            }
            OBN_Fiscal_Period_Posting_Guard::assert_can_post_or_fail($payment_date);
        }
        $payment_type = isset($_POST['payment_type_id']) ? sanitize_text_field($_POST['payment_type_id']) : sanitize_text_field($_POST['payment_type']);
        $account_id = intval($_POST['account_id']);
        $reference_no = sanitize_text_field($_POST['reference_no']);
        $payment_note = sanitize_textarea_field($_POST['payment_note']);
        $invoice_payments = isset($_POST['invoice_payments']) ? $_POST['invoice_payments'] : array();

        if ($id > 0) {
            // Fetch old record to get old amount and sales_id
            $old_payment = $wpdb->get_row($wpdb->prepare("SELECT salespayment_id, payment FROM $table WHERE id = %d", $id));

            $data = array(
                'customer_id' => $customer_id,
                'payment_date' => $payment_date,
                'payment_type' => $payment_type,
                'payment' => floatval($_POST['payment']),
                'payment_note' => $payment_note,
                'account_id' => $account_id,
                'reference_no' => $reference_no,
            );
            $wpdb->update($table, $data, array('id' => $id));
            self::create_customer_payment_journal_entry($id);

            // If linked to a sale, update sale balance
            if ($old_payment && !empty($old_payment->salespayment_id)) {
                $sale_id = intval($old_payment->salespayment_id);
                $old_amount = floatval($old_payment->payment);
                $new_amount = floatval($_POST['payment']);

                if ($old_amount != $new_amount) {
                    $sales_table = $wpdb->prefix . 'orabooks_db_sales';
                    $sale = $wpdb->get_row($wpdb->prepare("SELECT grand_total, paid_amount FROM $sales_table WHERE id = %d", $sale_id));
                    if ($sale) {
                        $new_paid = floatval($sale->paid_amount) - $old_amount + $new_amount;
                        if ($new_paid < 0)
                            $new_paid = 0;

                        $payment_status = ($new_paid >= floatval($sale->grand_total)) ? 'Paid' : 'Partial';
                        if ($new_paid == 0)
                            $payment_status = 'Due';

                        $wpdb->update(
                            $sales_table,
                            array('paid_amount' => $new_paid, 'payment_status' => $payment_status),
                            array('id' => $sale_id)
                        );
                    }
                }
            }

            wp_send_json_success(array('message' => 'Payment updated'));
        } else {
            // New payment - might be split across invoices
            if (empty($invoice_payments)) {
                // Fallback to single payment if no specific invoices selected
                $wpdb->insert($table, array(
                    'customer_id' => $customer_id,
                    'payment_date' => $payment_date,
                    'payment_type' => $payment_type,
                    'payment' => floatval($_POST['payment']),
                    'payment_note' => $payment_note,
                    'account_id' => $account_id,
                    'reference_no' => $reference_no,
                    'system_ip' => sanitize_text_field(getHostByName(getHostName())),
                    'system_name' => sanitize_text_field(gethostname()),
                    'created_date' => current_time('Y-m-d'),
                    'created_time' => current_time('H:i:s'),
                    'created_by' => get_current_user_id(),
                    'status' => 1
                ));
                $new_pay_id = $wpdb->insert_id;
                self::create_customer_payment_journal_entry($new_pay_id);
            } else {
                foreach ($invoice_payments as $sale_id => $amount) {
                    $amount = floatval($amount);
                    if ($amount <= 0)
                        continue;

                    // Create Primary Sales Payment Record
                    $payment_code = self::generate_cust_pay_code();
                    $wpdb->insert($wpdb->prefix . 'orabooks_db_salespayments', array(
                        'sales_id' => $sale_id,
                        'customer_id' => $customer_id,
                        'payment_date' => $payment_date,
                        'payment_type' => $payment_type,
                        'payment' => $amount,
                        'payment_note' => $payment_note,
                        'account_id' => $account_id,
                        'payment_code' => $payment_code,
                        'status' => 1,
                        'created_date' => current_time('Y-m-d'),
                        'created_time' => current_time('H:i:s'),
                        'system_ip' => sanitize_text_field($_SERVER['REMOTE_ADDR']),
                    ));
                    $primary_pay_id = $wpdb->insert_id;

                    // Insert payment record
                    $wpdb->insert($table, array(
                        'salespayment_id' => $primary_pay_id, // Store salespayments_id here
                        'customer_id' => $customer_id,
                        'payment_date' => $payment_date,
                        'payment_type' => $payment_type,
                        'payment' => $amount,
                        'payment_note' => $payment_note,
                        'account_id' => $account_id,
                        'reference_no' => $reference_no,
                        'system_ip' => sanitize_text_field(getHostByName(getHostName())),
                        'system_name' => sanitize_text_field(gethostname()),
                        'created_date' => current_time('Y-m-d'),
                        'created_time' => current_time('H:i:s'),
                        'created_by' => get_current_user_id(),
                        'status' => 1
                    ));
                    $new_pay_id = $wpdb->insert_id;
                    self::create_customer_payment_journal_entry($new_pay_id);

                    // Update Sales Table
                    $sale = $wpdb->get_row($wpdb->prepare("SELECT grand_total, paid_amount FROM $sales_table WHERE id = %d", $sale_id));
                    if ($sale) {
                        $new_paid = floatval($sale->paid_amount) + $amount;
                        $payment_status = ($new_paid >= floatval($sale->grand_total)) ? 'Paid' : 'Partial';
                        $wpdb->update(
                            $sales_table,
                            array('paid_amount' => $new_paid, 'payment_status' => $payment_status),
                            array('id' => $sale_id)
                        );
                    }
                }
            }
            wp_send_json_success(array('message' => 'Payment recorded successfully'));
        }
    }

    public static function handle_delete_customer_pay()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');
        global $wpdb;
        $id = intval($_POST['id']);
        $table = $wpdb->prefix . 'orabooks_db_customer_payments';
        $sales_table = $wpdb->prefix . 'orabooks_db_sales';

        // 1. Smart resolve sales_id and revert balance
        $payment = $wpdb->get_row($wpdb->prepare("SELECT salespayment_id, payment, customer_id, payment_date FROM $table WHERE id = %d", $id));
        if (!$payment) {
            wp_send_json_error('Payment not found');
        }
        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(obn_current_org_id(), $payment->payment_date);
            if (is_wp_error($modification_allowed)) {
                wp_send_json_error($modification_allowed->get_error_message(), 409);
            }
        }

        if ($payment && !empty($payment->salespayment_id)) {
            $payment_link_id = intval($payment->salespayment_id);
            $amount = floatval($payment->payment);
            $customer_id = intval($payment->customer_id);
            $sales_id = 0;

            // Try to find if it's a salespayments_id
            $sp_row = $wpdb->get_row($wpdb->prepare("SELECT sales_id FROM {$wpdb->prefix}orabooks_db_salespayments WHERE id = %d", $payment_link_id));
            if ($sp_row) {
                $sales_id = intval($sp_row->sales_id);
                // Also delete the link in salespayments
                $wpdb->delete($wpdb->prefix . 'orabooks_db_salespayments', array('id' => $payment_link_id));
            } else {
                // Assume it's a direct sales_id
                $sales_id = $payment_link_id;
            }

            $sale = $wpdb->get_row($wpdb->prepare("SELECT grand_total, paid_amount FROM $sales_table WHERE id = %d", $sales_id));
            if ($sale) {
                $new_paid = floatval($sale->paid_amount) - $amount;
                if ($new_paid < 0)
                    $new_paid = 0;

                $payment_status = 'Due';
                if ($new_paid > 0) {
                    $payment_status = ($new_paid >= floatval($sale->grand_total)) ? 'Paid' : 'Partial';
                }

                $wpdb->update(
                    $sales_table,
                    array('paid_amount' => $new_paid, 'payment_status' => $payment_status),
                    array('id' => $sales_id)
                );
            }
        }

        // 2. Delete the payment record
        $existing_entry_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_ac_journal_entry WHERE source_type = 'Customer Payment' AND source_id = %d", $id));
        if ($existing_entry_id) {
            if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                OBN_Fiscal_Period_Posting_Guard::assert_journal_entry_modifiable_or_fail($existing_entry_id);
            }
            $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_line', ['journal_entry_id' => $existing_entry_id]);
            $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_entry', ['id' => $existing_entry_id]);
        }

        $wpdb->delete($table, array('id' => $id));
        wp_send_json_success();
    }

    public static function handle_update_customer_pay_status()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');
        global $wpdb;
        $id = intval($_POST['id']);
        $table = $wpdb->prefix . 'orabooks_db_customer_payments';
        $payment = $wpdb->get_row($wpdb->prepare("SELECT payment_date FROM $table WHERE id = %d", $id));
        if (!$payment) {
            wp_send_json_error('Payment not found');
        }
        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(obn_current_org_id(), $payment->payment_date);
            if (is_wp_error($modification_allowed)) {
                wp_send_json_error($modification_allowed->get_error_message(), 409);
            }
        }
        $wpdb->update(
            $table,
            array('status' => intval($_POST['status'])),
            array('id' => $id)
        );
        wp_send_json_success();
    }

    public static function handle_get_customer_invoices()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');
        global $wpdb;
        $customer_id = intval($_POST['customer_id']);

        // Fetch unpaid or partially paid sales for this customer
        $sales_table = $wpdb->prefix . 'orabooks_db_sales';
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT id, sales_code as voucher_no, sales_date as due_date, grand_total as total, 
            (grand_total - IFNULL(paid_amount, 0)) as balance 
            FROM $sales_table 
            WHERE customer_id = %d AND status = 1 AND payment_status != 'Paid'
            ORDER BY sales_date ASC",
            $customer_id
        ));

        wp_send_json_success($results);
    }

    /* ========================= SUPPLIER PAYMENTS ========================= */

    public static function handle_save_supplier_pay()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_supplier_payments';
        $purchase_table = $wpdb->prefix . 'orabooks_db_purchase';

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $supplier_id = intval($_POST['supplier_id']);
        $payment_date = sanitize_text_field($_POST['payment_date']);
        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            if ($id > 0) {
                $existing_payment = $wpdb->get_row($wpdb->prepare("SELECT payment_date FROM $table WHERE id = %d", $id));
                if (!$existing_payment) {
                    wp_send_json_error('Payment not found');
                }
                $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(obn_current_org_id(), $existing_payment->payment_date);
                if (is_wp_error($modification_allowed)) {
                    wp_send_json_error($modification_allowed->get_error_message(), 409);
                }
            }
            OBN_Fiscal_Period_Posting_Guard::assert_can_post_or_fail($payment_date);
        }
        $payment_type = sanitize_text_field($_POST['payment_type']);
        $account_id = intval($_POST['account_id']);
        $reference_no = sanitize_text_field($_POST['reference_no']);
        $payment_note = sanitize_textarea_field($_POST['payment_note']);
        $invoice_payments = isset($_POST['invoice_payments']) ? $_POST['invoice_payments'] : array();

        if ($id > 0) {
            // Fetch old record to get old amount and purchase_id
            $old_payment = $wpdb->get_row($wpdb->prepare("SELECT purchasepayment_id, payment FROM $table WHERE id = %d", $id));

            $data = array(
                'supplier_id' => $supplier_id,
                'payment_date' => $payment_date,
                'payment_type' => $payment_type,
                'payment' => floatval($_POST['payment']),
                'payment_note' => $payment_note,
                'account_id' => $account_id,
                'reference_no' => $reference_no,
            );
            $wpdb->update($table, $data, array('id' => $id));

            // If linked to a purchase, update purchase balance
            if ($old_payment && !empty($old_payment->purchasepayment_id)) {
                $bridge_id = intval($old_payment->purchasepayment_id);
                $old_amount = floatval($old_payment->payment);
                $new_amount = floatval($_POST['payment']);

                if ($old_amount != $new_amount) {
                    // 1. Update the bridge table record
                    $wpdb->update(
                        $wpdb->prefix . 'orabooks_db_purchasepayments',
                        array('payment' => $new_amount),
                        array('id' => $bridge_id)
                    );

                    // 2. Resolve actual purchase_id
                    $purchase_id = $wpdb->get_var($wpdb->prepare("SELECT purchase_id FROM {$wpdb->prefix}orabooks_db_purchasepayments WHERE id = %d", $bridge_id));

                    if ($purchase_id) {
                        $purchase = $wpdb->get_row($wpdb->prepare("SELECT grand_total, IFNULL(paid_amount, 0) as paid_amount FROM $purchase_table WHERE id = %d", $purchase_id));
                        if ($purchase) {
                            $new_paid = floatval($purchase->paid_amount) - $old_amount + $new_amount;
                            if ($new_paid < 0)
                                $new_paid = 0;

                            $payment_status = ($new_paid >= floatval($purchase->grand_total)) ? 'Paid' : 'Partial';
                            if ($new_paid == 0)
                                $payment_status = 'Due';

                            $wpdb->update(
                                $purchase_table,
                                array('paid_amount' => $new_paid, 'payment_status' => $payment_status),
                                array('id' => $purchase_id)
                            );
                        }
                    }
                }
            }

            self::create_supplier_payment_journal_entry($id);
            wp_send_json_success(array('message' => 'Payment updated'));
        } else {
            if (empty($invoice_payments)) {
                $wpdb->insert($table, array(
                    'supplier_id' => $supplier_id,
                    'payment_date' => $payment_date,
                    'payment_type' => $payment_type,
                    'payment' => floatval($_POST['payment']),
                    'payment_note' => $payment_note,
                    'account_id' => $account_id,
                    'reference_no' => $reference_no,
                    'system_ip' => sanitize_text_field(getHostByName(getHostName())),
                    'system_name' => sanitize_text_field(gethostname()),
                    'created_date' => current_time('Y-m-d'),
                    'created_time' => current_time('H:i:s'),
                    'created_by' => get_current_user_id(),
                    'status' => 1
                ));
                $new_pay_id = $wpdb->insert_id;
                self::create_supplier_payment_journal_entry($new_pay_id);
            } else {
                foreach ($invoice_payments as $purchase_id => $amount) {
                    $amount = floatval($amount);
                    if ($amount <= 0)
                        continue;

                    // Create Primary Purchase Payment Record
                    $payment_code = self::generate_sup_pay_code();
                    $wpdb->insert($wpdb->prefix . 'orabooks_db_purchasepayments', array(
                        'purchase_id' => $purchase_id,
                        'supplier_id' => $supplier_id,
                        'payment_date' => $payment_date,
                        'payment_type' => $payment_type,
                        'payment' => $amount,
                        'payment_note' => $payment_note,
                        'account_id' => $account_id,
                        'payment_code' => $payment_code,
                        'status' => 1,
                        'created_date' => current_time('Y-m-d'),
                        'created_time' => current_time('H:i:s'),
                        'system_ip' => sanitize_text_field($_SERVER['REMOTE_ADDR']),
                    ));
                    $primary_pay_id = $wpdb->insert_id;

                    $wpdb->insert($table, array(
                        'purchasepayment_id' => $primary_pay_id,
                        'supplier_id' => $supplier_id,
                        'payment_date' => $payment_date,
                        'payment_type' => $payment_type,
                        'payment' => $amount,
                        'payment_note' => $payment_note,
                        'account_id' => $account_id,
                        'reference_no' => $reference_no,
                        'system_ip' => sanitize_text_field(getHostByName(getHostName())),
                        'system_name' => sanitize_text_field(gethostname()),
                        'created_date' => current_time('Y-m-d'),
                        'created_time' => current_time('H:i:s'),
                        'created_by' => get_current_user_id(),
                        'status' => 1
                    ));
                    $new_pay_id = $wpdb->insert_id;
                    self::create_supplier_payment_journal_entry($new_pay_id);

                    // Update Purchase Table
                    $purchase = $wpdb->get_row($wpdb->prepare("SELECT grand_total, paid_amount FROM $purchase_table WHERE id = %d", $purchase_id));
                    if ($purchase) {
                        $new_paid = floatval($purchase->paid_amount) + $amount;
                        $payment_status = ($new_paid >= floatval($purchase->grand_total)) ? 'Paid' : 'Partial';
                        $wpdb->update(
                            $purchase_table,
                            array('paid_amount' => $new_paid, 'payment_status' => $payment_status),
                            array('id' => $purchase_id)
                        );
                    }
                }
            }
            wp_send_json_success(array('message' => 'Payment recorded successfully'));
        }
    }

    public static function handle_delete_supplier_pay()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');
        global $wpdb;
        $id = intval($_POST['id']);
        $table = $wpdb->prefix . 'orabooks_db_supplier_payments';
        $purchase_table = $wpdb->prefix . 'orabooks_db_purchase';

        // 1. Smart resolve purchase_id and revert balance
        $payment = $wpdb->get_row($wpdb->prepare("SELECT purchasepayment_id, payment, supplier_id, payment_date FROM $table WHERE id = %d", $id));
        if (!$payment) {
            wp_send_json_error('Payment not found');
        }
        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(obn_current_org_id(), $payment->payment_date);
            if (is_wp_error($modification_allowed)) {
                wp_send_json_error($modification_allowed->get_error_message(), 409);
            }
        }

        if ($payment && !empty($payment->purchasepayment_id)) {
            $payment_link_id = intval($payment->purchasepayment_id);
            $amount = floatval($payment->payment);
            $supplier_id = intval($payment->supplier_id);
            $purchase_id = 0;

            // Try to find if it's a purchasepayment_id
            $pp_row = $wpdb->get_row($wpdb->prepare("SELECT purchase_id FROM {$wpdb->prefix}orabooks_db_purchasepayments WHERE id = %d", $payment_link_id));
            if ($pp_row) {
                $purchase_id = intval($pp_row->purchase_id);
                // Also delete the link in purchasepayments
                $wpdb->delete($wpdb->prefix . 'orabooks_db_purchasepayments', array('id' => $payment_link_id));
            } else {
                // Assume it's a direct purchase_id
                $purchase_id = $payment_link_id;
            }

            $purchase = $wpdb->get_row($wpdb->prepare("SELECT grand_total, paid_amount FROM $purchase_table WHERE id = %d", $purchase_id));
            if ($purchase) {
                $new_paid = floatval($purchase->paid_amount) - $amount;
                if ($new_paid < 0)
                    $new_paid = 0;

                $payment_status = 'Due';
                if ($new_paid > 0) {
                    $payment_status = ($new_paid >= floatval($purchase->grand_total)) ? 'Paid' : 'Partial';
                }

                $wpdb->update(
                    $purchase_table,
                    array('paid_amount' => $new_paid, 'payment_status' => $payment_status),
                    array('id' => $purchase_id)
                );
            }
        }

        // 2. Delete associated Journal Entry
        $existing_entry_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_ac_journal_entry WHERE source_type = 'Supplier Payment' AND source_id = %d", $id));
        if ($existing_entry_id) {
            if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                OBN_Fiscal_Period_Posting_Guard::assert_journal_entry_modifiable_or_fail($existing_entry_id);
            }
            $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_line', ['journal_entry_id' => $existing_entry_id]);
            $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_entry', ['id' => $existing_entry_id]);
        }

        // 3. Delete the payment record
        $wpdb->delete($table, array('id' => $id));
        wp_send_json_success();
    }

    public static function handle_update_supplier_pay_status()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');
        global $wpdb;
        $id = intval($_POST['id']);
        $table = $wpdb->prefix . 'orabooks_db_supplier_payments';
        $payment = $wpdb->get_row($wpdb->prepare("SELECT payment_date FROM $table WHERE id = %d", $id));
        if (!$payment) {
            wp_send_json_error('Payment not found');
        }
        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(obn_current_org_id(), $payment->payment_date);
            if (is_wp_error($modification_allowed)) {
                wp_send_json_error($modification_allowed->get_error_message(), 409);
            }
        }
        $wpdb->update(
            $table,
            array('status' => intval($_POST['status'])),
            array('id' => $id)
        );
        wp_send_json_success();
    }

    public static function handle_get_supplier_invoices()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');
        global $wpdb;
        $supplier_id = intval($_POST['supplier_id']);
        $table = $wpdb->prefix . 'orabooks_db_purchase';

        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, purchase_code as voucher_no, purchase_date as due_date, grand_total as total, (grand_total - paid_amount) as balance FROM $table WHERE supplier_id = %d AND status = 1 AND payment_status != 'Paid' ORDER BY id DESC", $supplier_id));

        wp_send_json_success($rows);
    }

    private static function generate_sup_pay_code()
    {
        global $wpdb;
        $prefix = $wpdb->get_var("SELECT purchase_payment_init FROM {$wpdb->prefix}orabooks_db_store LIMIT 1") ?: 'PP-';
        $last = $wpdb->get_var($wpdb->prepare("SELECT payment_code FROM {$wpdb->prefix}orabooks_db_purchasepayments WHERE payment_code LIKE %s ORDER BY id DESC LIMIT 1", $prefix . '%'));
        $next = 1;
        if ($last) {
            $num = str_replace($prefix, '', $last);
            if (is_numeric($num))
                $next = intval($num) + 1;
        }
        return $prefix . str_pad($next, 6, '0', STR_PAD_LEFT);
    }

    private static function generate_cust_pay_code()
    {
        global $wpdb;
        $prefix = $wpdb->get_var("SELECT sales_payment_init FROM {$wpdb->prefix}orabooks_db_store LIMIT 1") ?: 'SP-';
        $last = $wpdb->get_var($wpdb->prepare("SELECT payment_code FROM {$wpdb->prefix}orabooks_db_salespayments WHERE payment_code LIKE %s ORDER BY id DESC LIMIT 1", $prefix . '%'));
        $next = 1;
        if ($last) {
            $num = str_replace($prefix, '', $last);
            if (is_numeric($num))
                $next = intval($num) + 1;
        }
        return $prefix . str_pad($next, 6, '0', STR_PAD_LEFT);
    }

    public static function create_customer_payment_journal_entry($payment_id)
    {
        global $wpdb;

        $payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_customer_payments WHERE id = %d", $payment_id));
        if (!$payment)
            return;

        $customer_id = $payment->customer_id;
        $amount = floatval($payment->payment);
        $payment_date = $payment->payment_date;
        $account_id = intval($payment->account_id);
        $reference_no = $payment->reference_no;

        $customer = $wpdb->get_row($wpdb->prepare("SELECT store_id, customer_name FROM {$wpdb->prefix}orabooks_db_customers WHERE id = %d", $customer_id));
        $store_id = $customer ? $customer->store_id : 1;
        if (!$store_id)
            $store_id = 1;

        $currency_code = $wpdb->get_var($wpdb->prepare("SELECT currency_code FROM {$wpdb->prefix}orabooks_db_currency WHERE id = (SELECT currency_id FROM {$wpdb->prefix}orabooks_db_store WHERE id = %d LIMIT 1)", $store_id));
        if (!$currency_code)
            $currency_code = 'BDT';

        // Find Accounts Receivable Account
        $ar_account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_code IN ('1100', '1200') OR account_name LIKE '%Accounts Receivable%' OR account_name LIKE '%Debtors%') AND status=1 LIMIT 1");

        // Smart Account Resolution for DEBIT (Cash/Bank)
        $resolved_coa_id = 0;
        $pt_id = intval($payment->payment_type);
        $pt_name = $wpdb->get_var($wpdb->prepare("SELECT payment_type FROM {$wpdb->prefix}orabooks_db_paymenttypes WHERE id = %d", $pt_id));
        if (!$pt_name)
            $pt_name = 'Cash';
        $payment_type_str = $pt_name;

        if ($account_id > 0) {
            // The provided account_id is a Bank/Cash Account ID from orabooks_ac_accounts, need to find its COA ID
            $acc = $wpdb->get_row($wpdb->prepare("SELECT account_name, account_code FROM {$wpdb->prefix}orabooks_ac_accounts WHERE id = %d", $account_id));
            if ($acc) {
                $resolved_coa_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list 
                    WHERE (account_name = %s OR account_code = %s) AND status = 1 LIMIT 1",
                    $acc->account_name,
                    $acc->account_code
                ));
            }
        }

        if (!$resolved_coa_id) {
            // Fallback resolution based on payment type name
            $resolved_coa_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE account_name = %s AND status = 1 LIMIT 1", $pt_name));
            if (!$resolved_coa_id) {
                if (stripos($pt_name, 'Bank') !== false) {
                    $resolved_coa_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE account_code = '91' AND status = 1 LIMIT 1");
                } else {
                    $resolved_coa_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE account_code = '90' AND status = 1 LIMIT 1");
                }
            }
        }

        if (!$ar_account_id || !$resolved_coa_id)
            return;

        $entry_number = 'JE-CPAY-' . str_pad($payment_id, 6, '0', STR_PAD_LEFT);

        // Delete existing entry if any
        $existing_entry_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_ac_journal_entry WHERE source_type = 'Customer Payment' AND source_id = %d", $payment_id));
        if ($existing_entry_id) {
            if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                OBN_Fiscal_Period_Posting_Guard::assert_journal_entry_modifiable_or_fail($existing_entry_id);
            }
            $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_line', ['journal_entry_id' => $existing_entry_id]);
            $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_entry', ['id' => $existing_entry_id]);
        }

        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            $posting_allowed = OBN_Fiscal_Period_Posting_Guard::can_post(obn_current_org_id(), $payment_date);
            if (is_wp_error($posting_allowed)) {
                wp_send_json_error($posting_allowed->get_error_message(), 409);
            }
        }

        // Insert Journal Entry
        $entry_data = [
            'store_id' => $store_id,
            'organization_id' => obn_current_org_id(),
            'entry_number' => $entry_number,
            'entry_date' => $payment_date,
            'posting_date' => $payment_date,
            'document_date' => $payment_date,
            'reference_no' => $reference_no,
            'description' => 'Customer Payment Journal Entry - ' . ($reference_no ?: $payment_id) . ' (' . ($customer ? $customer->customer_name : '') . ')',
            'source_type' => 'Customer Payment',
            'source_id' => $payment_id,
            'status' => 'Posted',
            'currency' => $currency_code,
            'base_currency' => $currency_code,
            'total_debit' => $amount,
            'total_credit' => $amount,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'posted_at' => current_time('mysql'),
            'locked' => 1
        ];

        $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_entry', $entry_data);
        $journal_entry_id = $wpdb->insert_id;

        if (!$journal_entry_id)
            return;

        // DEBIT: Bank or Cash (The resolved account)
        $payment_type_desc = ($payment_type_str ?: 'Account') . ' Payment Received';
        $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
            'journal_entry_id' => $journal_entry_id,
            'account_id' => $resolved_coa_id,
            'contact_id' => $customer_id,
            'description' => $payment_type_desc,
            'debit' => $amount,
            'credit' => 0,
            'debit_amt' => $amount,
            'credit_amt' => 0,
            'currency' => $currency_code,
            'exchange_rate' => 1,
            'amount_base' => $amount,
            'line_number' => 1,
            'status' => 1
        ]);

        // CREDIT: Accounts Receivable
        $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
            'journal_entry_id' => $journal_entry_id,
            'account_id' => $ar_account_id,
            'contact_id' => $customer_id,
            'description' => 'Accounts Receivable',
            'debit' => 0,
            'credit' => $amount,
            'debit_amt' => 0,
            'credit_amt' => $amount,
            'currency' => $currency_code,
            'exchange_rate' => 1,
            'amount_base' => $amount,
            'line_number' => 2,
            'status' => 1
        ]);
    }

    public static function create_supplier_payment_journal_entry($payment_id)
    {
        global $wpdb;

        $payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_supplier_payments WHERE id = %d", $payment_id));
        if (!$payment)
            return;

        $supplier_id = $payment->supplier_id;
        $amount = floatval($payment->payment);
        $payment_date = $payment->payment_date;
        $account_id = intval($payment->account_id);
        $reference_no = $payment->reference_no;

        $supplier = $wpdb->get_row($wpdb->prepare("SELECT store_id, supplier_name FROM {$wpdb->prefix}orabooks_db_suppliers WHERE id = %d", $supplier_id));
        $store_id = $supplier ? $supplier->store_id : 1;
        if (!$store_id)
            $store_id = 1;

        $currency_code = $wpdb->get_var($wpdb->prepare("SELECT currency_code FROM {$wpdb->prefix}orabooks_db_currency WHERE id = (SELECT currency_id FROM {$wpdb->prefix}orabooks_db_store WHERE id = %d LIMIT 1)", $store_id));
        if (!$currency_code)
            $currency_code = 'BDT';

        // Find Accounts Payable Account
        $ap_account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_code IN ('2000', '2100') OR account_name LIKE '%Accounts Payable%' OR account_name LIKE '%Creditors%') AND status=1 LIMIT 1");

        // Smart Account Resolution
        $resolved_coa_id = 0;
        $pt_id = intval($payment->payment_type);
        $pt_name = $wpdb->get_var($wpdb->prepare("SELECT payment_type FROM {$wpdb->prefix}orabooks_db_paymenttypes WHERE id = %d", $pt_id));
        if (!$pt_name)
            $pt_name = 'Cash';
        $payment_type_str = $pt_name;

        if ($account_id > 0) {
            // The provided account_id is a Bank Account ID, need to find its COA ID
            $bank_account = $wpdb->get_row($wpdb->prepare("SELECT account_name, account_code FROM {$wpdb->prefix}orabooks_ac_accounts WHERE id = %d", $account_id));
            if ($bank_account) {
                // Try to find matching COA by name or code
                $resolved_coa_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list 
                    WHERE (account_name = %s OR account_code = %s) AND status = 1 LIMIT 1",
                    $bank_account->account_name,
                    $bank_account->account_code
                ));
            }
        }

        if (!$resolved_coa_id) {
            // Smart resolution based on payment type if no account selected or COA not found
            // 1. Try exact match by payment type name first
            $resolved_coa_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE account_name = %s AND status = 1 LIMIT 1", $pt_name));

            // 2. Fallback to standard codes
            if (!$resolved_coa_id) {
                if (stripos($pt_name, 'Bank') !== false) {
                    $resolved_coa_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE account_code = '91' AND status = 1 LIMIT 1");
                } else {
                    $resolved_coa_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE account_code = '90' AND status = 1 LIMIT 1");
                }
            }
        }

        if (!$ap_account_id || !$resolved_coa_id)
            return;

        $account_id = $resolved_coa_id; // Use the resolved COA ID for the journal entry

        $entry_number = 'JE-SPAY-' . str_pad($payment_id, 6, '0', STR_PAD_LEFT);

        // Delete existing entry if any
        $existing_entry_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_ac_journal_entry WHERE source_type = 'Supplier Payment' AND source_id = %d", $payment_id));
        if ($existing_entry_id) {
            if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                OBN_Fiscal_Period_Posting_Guard::assert_journal_entry_modifiable_or_fail($existing_entry_id);
            }
            $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_line', ['journal_entry_id' => $existing_entry_id]);
            $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_entry', ['id' => $existing_entry_id]);
        }

        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            $posting_allowed = OBN_Fiscal_Period_Posting_Guard::can_post(obn_current_org_id(), $payment_date);
            if (is_wp_error($posting_allowed)) {
                wp_send_json_error($posting_allowed->get_error_message(), 409);
            }
        }

        // Insert Journal Entry
        $entry_data = [
            'store_id' => $store_id,
            'organization_id' => obn_current_org_id(),
            'entry_number' => $entry_number,
            'entry_date' => $payment_date,
            'posting_date' => $payment_date,
            'document_date' => $payment_date,
            'reference_no' => $reference_no,
            'description' => 'Supplier Payment Journal Entry - ' . ($reference_no ?: $payment_id) . ' (' . ($supplier ? $supplier->supplier_name : '') . ')',
            'source_type' => 'Supplier Payment',
            'source_id' => $payment_id,
            'status' => 'Posted',
            'currency' => $currency_code,
            'base_currency' => $currency_code,
            'total_debit' => $amount,
            'total_credit' => $amount,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'posted_at' => current_time('mysql'),
            'locked' => 1
        ];

        $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_entry', $entry_data);
        $journal_entry_id = $wpdb->insert_id;

        if (!$journal_entry_id)
            return;

        // DEBIT: Accounts Payable
        $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
            'journal_entry_id' => $journal_entry_id,
            'account_id' => $ap_account_id,
            'contact_id' => $supplier_id,
            'description' => 'Accounts Payable',
            'debit' => $amount,
            'credit' => 0,
            'debit_amt' => $amount,
            'credit_amt' => 0,
            'currency' => $currency_code,
            'exchange_rate' => 1,
            'amount_base' => $amount,
            'line_number' => 1,
            'status' => 1
        ]);

        // CREDIT: Dynamic Payment Type (e.g. Cash Payment, Bank Payment)
        $payment_type_desc = ($payment_type_str ?: 'Account') . ' Payment';
        $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
            'journal_entry_id' => $journal_entry_id,
            'account_id' => $account_id,
            'contact_id' => $supplier_id,
            'description' => $payment_type_desc,
            'debit' => 0,
            'credit' => $amount,
            'debit_amt' => 0,
            'credit_amt' => $amount,
            'currency' => $currency_code,
            'exchange_rate' => 1,
            'amount_base' => $amount,
            'line_number' => 2,
            'status' => 1
        ]);
    }
}

OBN_Accounting_Contacts::init();
