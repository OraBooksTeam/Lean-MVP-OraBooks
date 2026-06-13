<?php

if (!defined('ABSPATH')) {
    exit;
}

class Frontend_Inventory_DB_Manager
{

    public static function create_table()
    {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // Store Profile Table
        $table_store = $wpdb->prefix . 'orabooks_db_store';
        $sql = "CREATE TABLE $table_store (
            id int(5) NOT NULL AUTO_INCREMENT,
            store_code varchar(50) DEFAULT NULL,
            store_name varchar(100) DEFAULT NULL,
            mobile varchar(55) DEFAULT NULL,
            email varchar(100) DEFAULT NULL,
            phone varchar(55) DEFAULT NULL,
            gst_no varchar(100) DEFAULT NULL,
            tax_number varchar(100) DEFAULT NULL,
            pan_no varchar(100) DEFAULT NULL,
            store_website varchar(100) DEFAULT NULL,
            bank_details mediumtext DEFAULT NULL,
            country varchar(100) DEFAULT NULL,
            state varchar(100) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            postcode varchar(50) DEFAULT NULL,
            address mediumtext DEFAULT NULL,
            footer_text text DEFAULT NULL,
            store_logo text DEFAULT NULL,
            signature_image text DEFAULT NULL,
            show_signature int(1) DEFAULT 0,
            currency_id int(5) DEFAULT NULL,
            currency_placement varchar(50) DEFAULT 'before',
            timezone varchar(100) DEFAULT NULL,
            date_format varchar(50) DEFAULT 'd-m-Y',
            time_format varchar(50) DEFAULT '12',
            decimals int(5) DEFAULT 2,
            qty_decimals int(5) DEFAULT 2,
            default_sales_discount double(10,2) DEFAULT 0.00,
            sales_invoice_format_id varchar(50) DEFAULT 'Standard',
            pos_invoice_format_id varchar(50) DEFAULT 'Standard',
            invoice_terms text DEFAULT NULL,
            total_items int(10) DEFAULT 0,
            language_id varchar(50) DEFAULT 'en',
            category_init varchar(50) DEFAULT 'CAT',
            supplier_init varchar(50) DEFAULT 'SUP',
            purchase_return_init varchar(50) DEFAULT 'PR',
            sales_init varchar(50) DEFAULT 'SL',
            expense_init varchar(50) DEFAULT 'EXP',
            quotation_init varchar(50) DEFAULT 'QT',
            sales_payment_init varchar(50) DEFAULT 'SP',
            purchase_payment_init varchar(50) DEFAULT 'PP',
            expense_payment_init varchar(50) DEFAULT 'EP',
            item_init varchar(50) DEFAULT 'IT',
            purchase_init varchar(50) DEFAULT 'PO',
            customer_init varchar(50) DEFAULT 'CUST',
            sales_return_init varchar(50) DEFAULT 'SR',
            accounts_init varchar(50) DEFAULT 'ACC',
            money_transfer_init varchar(50) DEFAULT 'MT',
            sales_return_payment_init varchar(50) DEFAULT 'SRP',
            purchase_return_payment_init varchar(50) DEFAULT 'PRP',
            cust_advance_init varchar(50) DEFAULT 'AD',
            sales_description text DEFAULT NULL,
            round_off int(1) DEFAULT 0,
            default_account_id int(5) DEFAULT NULL,
            sales_discount double(20,2) DEFAULT NULL,
            mrp_column int(11) DEFAULT 0,
            show_paid_change_pos int(1) DEFAULT 0,
            previous_balance_bit int(1) DEFAULT 0,
            number_to_words varchar(20) DEFAULT 'Indian',
            t_and_c_status text DEFAULT NULL,
            created_time varchar(50) DEFAULT NULL,
            created_date date DEFAULT NULL,
            created_by varchar(50) DEFAULT NULL,
            system_ip varchar(50) DEFAULT NULL,
            system_name varchar(100) DEFAULT NULL,
            status int(1) DEFAULT 1,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Warehouse Table
        $table_warehouse = $wpdb->prefix . 'orabooks_db_warehouse';
        $sql_warehouse = "CREATE TABLE $table_warehouse (
            id int(5) NOT NULL AUTO_INCREMENT,
            store_id int(11) DEFAULT NULL,
            warehouse_type varchar(50) DEFAULT 'custom',
            warehouse_name varchar(100) DEFAULT NULL,
            mobile varchar(50) DEFAULT NULL,
            email varchar(100) DEFAULT NULL,
            address text DEFAULT NULL,
            status int(1) DEFAULT 1,
            created_date datetime DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Items Table
        $table_items = $wpdb->prefix . 'orabooks_db_items';
        $sql_items = "CREATE TABLE $table_items (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `count_id` int(10) DEFAULT NULL,
            `item_code` varchar(50) DEFAULT NULL,
            `item_name` varchar(100) DEFAULT NULL,
            `category_id` int(10) DEFAULT NULL,
            `sku` varchar(50) DEFAULT NULL,
            `hsn` varchar(50) DEFAULT NULL,
            `sac` varchar(50) DEFAULT NULL,
            `unit_id` int(10) DEFAULT NULL,
            `alert_qty` double(20,2) DEFAULT NULL,
            `brand_id` int(5) DEFAULT NULL,
            `lot_number` varchar(50) DEFAULT NULL,
            `expire_date` date DEFAULT NULL,
            `price` double(20,4) DEFAULT NULL,
            `tax_id` int(5) DEFAULT NULL,
            `purchase_price` double(20,4) DEFAULT NULL,
            `tax_type` varchar(50) DEFAULT NULL,
            `profit_margin` double(20,2) DEFAULT NULL,
            `sales_price` double(20,4) DEFAULT NULL,
            `stock` double(20,2) DEFAULT NULL,
            `item_image` text DEFAULT NULL,
            `system_ip` varchar(50) DEFAULT NULL,
            `system_name` varchar(50) DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(50) DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `company_id` int(5) DEFAULT NULL,
            `status` int(5) DEFAULT NULL,
            `discount_type` varchar(100) DEFAULT 'Percentage',
            `discount` double(20,2) DEFAULT 0.00,
            `service_bit` int(1) DEFAULT 0,
            `seller_points` double(20,2) DEFAULT 0.00,
            `custom_barcode` varchar(100) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `item_type` varchar(50) DEFAULT NULL,
            `parent_id` int(5) DEFAULT NULL,
            `variant_id` int(5) DEFAULT NULL,
            `child_bit` int(1) DEFAULT 0,
            `mrp` double(20,4) DEFAULT NULL,
            `warehouse_id` int(11) DEFAULT NULL,
            `purchase_account_id` int(11) DEFAULT NULL,
            `sales_account_id` int(11) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Combine SQLs
        $sql .= "\n" . $sql_warehouse;
        $sql .= "\n" . $sql_items;

        // Customers Table
        $table_customers = $wpdb->prefix . 'orabooks_db_customers';
        $sql .= "\nCREATE TABLE $table_customers (
            id int(5) NOT NULL AUTO_INCREMENT,
            store_id int(11) DEFAULT NULL,
            count_id int(10) DEFAULT NULL,
            customer_code varchar(50) DEFAULT NULL,
            customer_name varchar(100) DEFAULT NULL,
            mobile varchar(55) DEFAULT NULL,
            email varchar(100) DEFAULT NULL,
            phone varchar(55) DEFAULT NULL,
            gstin varchar(100) DEFAULT NULL,
            tax_number varchar(100) DEFAULT NULL,
            credit_limit double(20,2) DEFAULT NULL,
            opening_balance double(20,2) DEFAULT NULL,
            country_id varchar(100) DEFAULT NULL,
            state_id varchar(100) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            postcode varchar(50) DEFAULT NULL,
            address text DEFAULT NULL,
            ship_country_id varchar(100) DEFAULT NULL,
            ship_state_id varchar(100) DEFAULT NULL,
            ship_city varchar(100) DEFAULT NULL,
            ship_postcode varchar(50) DEFAULT NULL,
            ship_address text DEFAULT NULL,
            location_link text DEFAULT NULL,
            attachment_1 text DEFAULT NULL,
            price_level_type varchar(50) DEFAULT 'Increase',
            price_level double(20,2) DEFAULT NULL,
            created_date date DEFAULT NULL,
            created_time varchar(50) DEFAULT NULL,
            created_by varchar(50) DEFAULT NULL,
            system_ip varchar(50) DEFAULT NULL,
            system_name varchar(100) DEFAULT NULL,
            status int(1) DEFAULT 1,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Suppliers Table
        $table_suppliers = $wpdb->prefix . 'orabooks_db_suppliers';
        $sql .= "\nCREATE TABLE $table_suppliers (
            id int(5) NOT NULL AUTO_INCREMENT,
            store_id int(11) DEFAULT NULL,
            count_id int(10) DEFAULT NULL,
            supplier_code varchar(50) DEFAULT NULL,
            supplier_name varchar(100) DEFAULT NULL,
            mobile varchar(55) DEFAULT NULL,
            email varchar(100) DEFAULT NULL,
            phone varchar(55) DEFAULT NULL,
            gstin varchar(100) DEFAULT NULL,
            tax_number varchar(100) DEFAULT NULL,
            opening_balance double(20,2) DEFAULT NULL,
            country_id varchar(100) DEFAULT NULL,
            state_id varchar(100) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            postcode varchar(50) DEFAULT NULL,
            address text DEFAULT NULL,
            created_date date DEFAULT NULL,
            created_time varchar(50) DEFAULT NULL,
            created_by varchar(50) DEFAULT NULL,
            system_ip varchar(50) DEFAULT NULL,
            system_name varchar(100) DEFAULT NULL,
            status int(1) DEFAULT 1,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Currency Table
        $table_currency = $wpdb->prefix . 'orabooks_db_currency';
        $sql .= "\nCREATE TABLE $table_currency (
            id int(5) NOT NULL AUTO_INCREMENT,
            store_id int(11) DEFAULT NULL,
            currency_name varchar(100) DEFAULT NULL,
            currency_code varchar(50) DEFAULT NULL,
            currency_symbol varchar(50) DEFAULT NULL,
            status int(1) DEFAULT 1,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Brands Table
        $table_brands = $wpdb->prefix . 'orabooks_db_brands';
        $sql .= "\nCREATE TABLE $table_brands (
            id int(5) NOT NULL AUTO_INCREMENT,
            store_id int(11) DEFAULT NULL,
            brand_code varchar(50) DEFAULT NULL,
            brand_name varchar(100) DEFAULT NULL,
            description mediumtext DEFAULT NULL,
            status int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Category Table
        $table_category = $wpdb->prefix . 'orabooks_db_category';
        $sql .= "\nCREATE TABLE $table_category (
            id int(5) NOT NULL AUTO_INCREMENT,
            store_id int(11) DEFAULT NULL,
            count_id int(10) DEFAULT NULL,
            category_code varchar(50) DEFAULT NULL,
            category_name varchar(100) DEFAULT NULL,
            description mediumtext DEFAULT NULL,
            company_id int(5) DEFAULT NULL,
            status int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Units Table
        $table_units = $wpdb->prefix . 'orabooks_db_units';
        $sql .= "\nCREATE TABLE $table_units (
            id int(5) NOT NULL AUTO_INCREMENT,
            store_id int(11) DEFAULT NULL,
            unit_name varchar(50) DEFAULT NULL,
            description mediumtext DEFAULT NULL,
            status int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Tax Table
        $table_tax = $wpdb->prefix . 'orabooks_db_tax';
        $sql .= "\nCREATE TABLE $table_tax (
            id int(5) NOT NULL AUTO_INCREMENT,
            store_id int(11) DEFAULT NULL,
            tax_name varchar(50) DEFAULT NULL,
            tax double(10,2) DEFAULT NULL,
            status int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Variants Table
        $table_variants = $wpdb->prefix . 'orabooks_db_variants';
        $sql .= "\nCREATE TABLE $table_variants (
            id int(5) NOT NULL AUTO_INCREMENT,
            store_id int(11) DEFAULT NULL,
            variant_code varchar(50) DEFAULT NULL,
            variant_name varchar(100) DEFAULT NULL,
            description mediumtext DEFAULT NULL,
            status int(1) DEFAULT 1,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Payment Types Table
        $table_paymenttypes = $wpdb->prefix . 'orabooks_db_paymenttypes';
        $sql .= "\nCREATE TABLE $table_paymenttypes (
            id int(5) NOT NULL AUTO_INCREMENT,
            store_id int(11) DEFAULT NULL,
            payment_type varchar(50) DEFAULT NULL,
            status int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Accounts Table
        $table_accounts = $wpdb->prefix . 'orabooks_ac_accounts';
        $sql .= "\nCREATE TABLE $table_accounts (
            id int(11) NOT NULL AUTO_INCREMENT,
            count_id int(5) DEFAULT NULL,
            store_id int(5) DEFAULT NULL,
            parent_id int(5) DEFAULT NULL,
            sort_code varchar(100) DEFAULT NULL,
            account_name varchar(50) DEFAULT NULL,
            account_code varchar(50) DEFAULT NULL,
            balance double(20,4) DEFAULT NULL,
            note text DEFAULT NULL,
            created_by varchar(50) DEFAULT NULL,
            created_date date DEFAULT NULL,
            created_time varchar(50) DEFAULT NULL,
            system_ip varchar(50) DEFAULT NULL,
            system_name varchar(50) DEFAULT NULL,
            status int(1) DEFAULT NULL,
            delete_bit int(1) DEFAULT 0,
            account_selection_name varchar(50) DEFAULT NULL,
            paymenttypes_id int(1) DEFAULT NULL,
            customer_id int(5) DEFAULT NULL,
            supplier_id int(5) DEFAULT NULL,
            expense_id int(5) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Sales Table
        $table_sales = $wpdb->prefix . 'orabooks_db_sales';
        $sql .= "\nCREATE TABLE $table_sales (
            id int(5) NOT NULL AUTO_INCREMENT,
            store_id int(11) DEFAULT NULL,
            warehouse_id int(5) DEFAULT NULL,
            init_code varchar(100) DEFAULT NULL,
            count_id decimal(20,0) DEFAULT NULL,
            sales_code varchar(50) DEFAULT NULL,
            reference_no varchar(50) DEFAULT NULL,
            sales_date date DEFAULT NULL,
            due_date date DEFAULT NULL,
            sales_status varchar(50) DEFAULT NULL,
            customer_id int(5) DEFAULT NULL,
            other_charges_input double(20,2) DEFAULT NULL,
            other_charges_tax_id int(5) DEFAULT NULL,
            other_charges_amt double(20,2) DEFAULT NULL,
            discount_to_all_input double(20,2) DEFAULT NULL,
            discount_to_all_type varchar(50) DEFAULT NULL,
            tot_discount_to_all_amt double(20,2) DEFAULT NULL,
            subtotal double(20,2) DEFAULT NULL,
            round_off double(20,2) DEFAULT NULL,
            grand_total double(20,4) DEFAULT NULL,
            sales_note text DEFAULT NULL,
            payment_status varchar(50) DEFAULT NULL,
            paid_amount double(20,4) DEFAULT NULL,
            created_date date DEFAULT NULL,
            created_time varchar(50) DEFAULT NULL,
            created_by varchar(50) DEFAULT NULL,
            system_ip varchar(100) DEFAULT NULL,
            system_name varchar(100) DEFAULT NULL,
            company_id int(5) DEFAULT NULL,
            pos int(1) DEFAULT NULL,
            status int(1) DEFAULT NULL,
            return_bit int(1) DEFAULT NULL,
            customer_previous_due double(20,2) DEFAULT NULL,
            customer_total_due double(20,2) DEFAULT NULL,
            quotation_id int(5) DEFAULT NULL,
            coupon_id int(10) DEFAULT NULL,
            coupon_amt double(20,2) DEFAULT 0.00,
            invoice_terms text DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Sales Items Table
        $table_salesitems = $wpdb->prefix . 'orabooks_db_salesitems';
        $sql .= "\nCREATE TABLE $table_salesitems (
            id int(5) NOT NULL AUTO_INCREMENT,
            store_id int(11) DEFAULT NULL,
            sales_id int(5) DEFAULT NULL,
            sales_status varchar(50) DEFAULT NULL,
            item_id int(5) DEFAULT NULL,
            description text DEFAULT NULL,
            sales_qty double(20,2) DEFAULT NULL,
            price_per_unit double(20,4) DEFAULT NULL,
            tax_type varchar(50) DEFAULT NULL,
            tax_id int(5) DEFAULT NULL,
            tax_amt double(20,4) DEFAULT NULL,
            discount_type varchar(50) DEFAULT NULL,
            discount_input double(20,4) DEFAULT NULL,
            discount_amt double(20,4) DEFAULT NULL,
            unit_total_cost double(20,4) DEFAULT NULL,
            total_cost double(20,4) DEFAULT NULL,
            status int(5) DEFAULT NULL,
            seller_points double(20,2) DEFAULT 0.00,
            purchase_price double(20,3) DEFAULT 0.000,
            account_id int(11) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Sales Payments Table
        $table_salespayments = $wpdb->prefix . 'orabooks_db_salespayments';
        $sql .= "\nCREATE TABLE $table_salespayments (
            id int(5) NOT NULL AUTO_INCREMENT,
            count_id int(5) DEFAULT NULL,
            payment_code varchar(50) DEFAULT NULL,
            store_id int(11) DEFAULT NULL,
            sales_id int(5) DEFAULT NULL,
            payment_date date DEFAULT NULL,
            payment_type varchar(50) DEFAULT NULL,
            payment double(20,4) DEFAULT NULL,
            payment_note text DEFAULT NULL,
            change_return double(20,4) DEFAULT NULL,
            system_ip varchar(50) DEFAULT NULL,
            system_name varchar(50) DEFAULT NULL,
            created_time varchar(50) DEFAULT NULL,
            created_date date DEFAULT NULL,
            created_by varchar(50) DEFAULT NULL,
            status int(1) DEFAULT NULL,
            account_id int(5) DEFAULT NULL,
            customer_id int(5) DEFAULT NULL,
            short_code varchar(50) DEFAULT NULL,
            advance_adjusted double(20,4) DEFAULT NULL,
            cheque_number varchar(100) DEFAULT NULL,
            cheque_period int(10) DEFAULT NULL,
            cheque_status varchar(100) DEFAULT NULL,
            mobile_number varchar(20) DEFAULT NULL,
            bank_name varchar(100) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Purchase Table
        $table_purchase = $wpdb->prefix . 'orabooks_db_purchase';
        $sql .= "\nCREATE TABLE $table_purchase (
            id int(5) NOT NULL AUTO_INCREMENT,
            store_id int(11) DEFAULT NULL,
            warehouse_id int(5) DEFAULT NULL,
            count_id int(10) DEFAULT NULL,
            purchase_code varchar(50) DEFAULT NULL,
            reference_no varchar(50) DEFAULT NULL,
            purchase_date date DEFAULT NULL,
            purchase_status varchar(50) DEFAULT NULL,
            supplier_id int(5) DEFAULT NULL,
            other_charges_input double(20,4) DEFAULT NULL,
            other_charges_tax_id int(5) DEFAULT NULL,
            other_charges_amt double(20,4) DEFAULT NULL,
            discount_to_all_input double(20,4) DEFAULT NULL,
            discount_to_all_type varchar(50) DEFAULT NULL,
            tot_discount_to_all_amt double(20,4) DEFAULT NULL,
            subtotal double(20,4) DEFAULT NULL,
            round_off double(20,4) DEFAULT NULL,
            grand_total double(20,4) DEFAULT NULL,
            purchase_note text DEFAULT NULL,
            payment_status varchar(50) DEFAULT NULL,
            paid_amount double(20,4) DEFAULT NULL,
            created_date date DEFAULT NULL,
            created_time varchar(50) DEFAULT NULL,
            created_by varchar(50) DEFAULT NULL,
            system_ip varchar(100) DEFAULT NULL,
            system_name varchar(100) DEFAULT NULL,
            company_id int(5) DEFAULT NULL,
            status int(1) DEFAULT NULL,
            return_bit int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Purchase Items Table
        $table_purchaseitems = $wpdb->prefix . 'orabooks_db_purchaseitems';
        $sql .= "\nCREATE TABLE $table_purchaseitems (
            id int(5) NOT NULL AUTO_INCREMENT,
            store_id int(11) DEFAULT NULL,
            purchase_id int(5) DEFAULT NULL,
            purchase_status varchar(50) DEFAULT NULL,
            item_id int(5) DEFAULT NULL,
            purchase_qty double(20,2) DEFAULT NULL,
            price_per_unit double(20,4) DEFAULT NULL,
            tax_type varchar(50) DEFAULT NULL,
            tax_id int(5) DEFAULT NULL,
            tax_amt double(20,4) DEFAULT NULL,
            discount_type varchar(50) DEFAULT NULL,
            discount_input double(20,4) DEFAULT NULL,
            discount_amt double(20,4) DEFAULT NULL,
            unit_total_cost double(20,4) DEFAULT NULL,
            total_cost double(20,4) DEFAULT NULL,
            profit_margin_per double(20,4) DEFAULT NULL,
            unit_sales_price double(20,4) DEFAULT NULL,
            status int(5) DEFAULT NULL,
            description text DEFAULT NULL,
            account_id int(11) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 42. Purchase Payments Table
        $table_purchasepayments = $wpdb->prefix . 'orabooks_db_purchasepayments';
        $sql_purchasepayments = "CREATE TABLE $table_purchasepayments (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `count_id` int(5) DEFAULT NULL,
            `payment_code` varchar(50) DEFAULT NULL,
            `store_id` int(11) DEFAULT NULL,
            `purchase_id` int(5) DEFAULT NULL,
            `payment_date` date DEFAULT NULL,
            `payment_type` varchar(50) DEFAULT NULL,
            `payment` double(20,4) DEFAULT NULL,
            `payment_note` text DEFAULT NULL,
            `system_ip` varchar(50) DEFAULT NULL,
            `system_name` varchar(50) DEFAULT NULL,
            `created_time` time DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            `account_id` int(5) DEFAULT NULL,
            `supplier_id` int(11) DEFAULT NULL,
            `short_code` varchar(50) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_purchasepayments);

        // Purchase Return Table
        $table_purchasereturn = $wpdb->prefix . 'orabooks_db_purchasereturn';
        $sql_purchasereturn = "CREATE TABLE $table_purchasereturn (
            id int(5) NOT NULL AUTO_INCREMENT,
            store_id int(11) DEFAULT NULL,
            warehouse_id int(5) DEFAULT NULL,
            purchase_id int(5) DEFAULT NULL,
            return_code varchar(50) DEFAULT NULL,
            reference_no varchar(50) DEFAULT NULL,
            return_date date DEFAULT NULL,
            return_status varchar(50) DEFAULT 'Pending',
            supplier_id int(5) DEFAULT NULL,
            other_charges_input double(20,4) DEFAULT NULL,
            other_charges_tax_id int(5) DEFAULT NULL,
            other_charges_amt double(20,4) DEFAULT NULL,
            discount_to_all_input double(20,4) DEFAULT NULL,
            discount_to_all_type varchar(50) DEFAULT NULL,
            tot_discount_to_all_amt double(20,4) DEFAULT NULL,
            subtotal double(20,4) DEFAULT NULL,
            round_off double(20,4) DEFAULT NULL,
            grand_total double(20,4) DEFAULT NULL,
            return_note text DEFAULT NULL,
            payment_status varchar(50) DEFAULT NULL,
            paid_amount double(20,4) DEFAULT NULL,
            created_date date DEFAULT NULL,
            created_time varchar(50) DEFAULT NULL,
            created_by varchar(50) DEFAULT NULL,
            approved_by varchar(50) DEFAULT NULL,
            approved_date date DEFAULT NULL,
            system_ip varchar(100) DEFAULT NULL,
            system_name varchar(100) DEFAULT NULL,
            status int(1) DEFAULT 1,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_purchasereturn);

        // Purchase Return Items Table
        $table_purchaseitemsreturn = $wpdb->prefix . 'orabooks_db_purchaseitemsreturn';
        $sql_purchaseitemsreturn = "CREATE TABLE $table_purchaseitemsreturn (
            id int(5) NOT NULL AUTO_INCREMENT,
            return_id int(5) DEFAULT NULL,
            item_id int(5) DEFAULT NULL,
            purchase_qty double(20,2) DEFAULT NULL,
            price_per_unit double(20,4) DEFAULT NULL,
            tax_type varchar(50) DEFAULT NULL,
            tax_id int(5) DEFAULT NULL,
            tax_amt double(20,4) DEFAULT NULL,
            discount_type varchar(50) DEFAULT NULL,
            discount_input double(20,4) DEFAULT NULL,
            discount_amt double(20,4) DEFAULT NULL,
            unit_total_cost double(20,4) DEFAULT NULL,
            total_cost double(20,4) DEFAULT NULL,
            account_id int(5) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_purchaseitemsreturn);

        // Purchase Return Payments Table
        $table_purchasepaymentsreturn = $wpdb->prefix . 'orabooks_db_purchasepaymentsreturn';
        $sql_purchasepaymentsreturn = "CREATE TABLE $table_purchasepaymentsreturn (
            id int(5) NOT NULL AUTO_INCREMENT,
            return_id int(5) DEFAULT NULL,
            payment_date date DEFAULT NULL,
            payment_type varchar(50) DEFAULT NULL,
            payment double(20,4) DEFAULT NULL,
            payment_note text DEFAULT NULL,
            account_id int(5) DEFAULT NULL,
            supplier_id int(11) DEFAULT NULL,
            created_date date DEFAULT NULL,
            created_by varchar(50) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_purchasepaymentsreturn);

        // Sales Return Table
        $table_salesreturn = $wpdb->prefix . 'orabooks_db_salesreturn';
        $sql_salesreturn = "CREATE TABLE $table_salesreturn (
            id int(5) NOT NULL AUTO_INCREMENT,
            store_id int(11) DEFAULT NULL,
            warehouse_id int(5) DEFAULT NULL,
            sales_id int(5) DEFAULT NULL,
            return_code varchar(50) DEFAULT NULL,
            reference_no varchar(50) DEFAULT NULL,
            return_date date DEFAULT NULL,
            return_status varchar(50) DEFAULT 'Pending',
            customer_id int(5) DEFAULT NULL,
            other_charges_input double(20,4) DEFAULT NULL,
            other_charges_tax_id int(5) DEFAULT NULL,
            other_charges_amt double(20,4) DEFAULT NULL,
            discount_to_all_input double(20,4) DEFAULT NULL,
            discount_to_all_type varchar(50) DEFAULT NULL,
            tot_discount_to_all_amt double(20,4) DEFAULT NULL,
            subtotal double(20,4) DEFAULT NULL,
            round_off double(20,4) DEFAULT NULL,
            grand_total double(20,4) DEFAULT NULL,
            return_note text DEFAULT NULL,
            payment_status varchar(50) DEFAULT NULL,
            paid_amount double(20,4) DEFAULT NULL,
            created_date date DEFAULT NULL,
            created_time varchar(50) DEFAULT NULL,
            created_by varchar(50) DEFAULT NULL,
            approved_by varchar(50) DEFAULT NULL,
            approved_date date DEFAULT NULL,
            system_ip varchar(100) DEFAULT NULL,
            system_name varchar(100) DEFAULT NULL,
            status int(1) DEFAULT 1,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_salesreturn);

        // Sales Return Items Table
        $table_salesitemsreturn = $wpdb->prefix . 'orabooks_db_salesitemsreturn';
        $sql_salesitemsreturn = "CREATE TABLE $table_salesitemsreturn (
            id int(5) NOT NULL AUTO_INCREMENT,
            return_id int(5) DEFAULT NULL,
            item_id int(5) DEFAULT NULL,
            sales_qty double(20,2) DEFAULT NULL,
            price_per_unit double(20,4) DEFAULT NULL,
            tax_type varchar(50) DEFAULT NULL,
            tax_id int(5) DEFAULT NULL,
            tax_amt double(20,4) DEFAULT NULL,
            discount_type varchar(50) DEFAULT NULL,
            discount_input double(20,4) DEFAULT NULL,
            discount_amt double(20,4) DEFAULT NULL,
            unit_total_cost double(20,4) DEFAULT NULL,
            total_cost double(20,4) DEFAULT NULL,
            account_id int(5) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_salesitemsreturn);

        // Sales Return Payments Table
        $table_salespaymentsreturn = $wpdb->prefix . 'orabooks_db_salespaymentsreturn';
        $sql_salespaymentsreturn = "CREATE TABLE $table_salespaymentsreturn (
            id int(5) NOT NULL AUTO_INCREMENT,
            return_id int(5) DEFAULT NULL,
            payment_date date DEFAULT NULL,
            payment_type varchar(50) DEFAULT NULL,
            payment double(20,4) DEFAULT NULL,
            payment_note text DEFAULT NULL,
            account_id int(5) DEFAULT NULL,
            customer_id int(11) DEFAULT NULL,
            created_date date DEFAULT NULL,
            created_by varchar(50) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_salespaymentsreturn);

        // Customer Payments Table
        $table_customerpayments = $wpdb->prefix . 'orabooks_db_customer_payments';
        $sql_customerpayments = "CREATE TABLE $table_customerpayments (
            id int(5) NOT NULL AUTO_INCREMENT,
            salespayment_id int(5) DEFAULT NULL,
            customer_id int(5) DEFAULT NULL,
            payment_date date DEFAULT NULL,
            payment_type varchar(50) DEFAULT NULL,
            payment double(20,4) DEFAULT NULL,
            payment_note text DEFAULT NULL,
            system_ip varchar(50) DEFAULT NULL,
            system_name varchar(50) DEFAULT NULL,
            created_time time DEFAULT NULL,
            created_date date DEFAULT NULL,
            created_by varchar(50) DEFAULT NULL,
            status int(1) DEFAULT 1,
            account_id int(5) DEFAULT NULL,
            reference_no varchar(50) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_customerpayments);

        // Supplier Payments Table
        $table_supplierpayments = $wpdb->prefix . 'orabooks_db_supplier_payments';
        $sql_supplierpayments = "CREATE TABLE $table_supplierpayments (
            id int(5) NOT NULL AUTO_INCREMENT,
            purchasepayment_id int(5) DEFAULT NULL,
            supplier_id int(5) DEFAULT NULL,
            payment_date date DEFAULT NULL,
            payment_type varchar(50) DEFAULT NULL,
            payment double(20,4) DEFAULT NULL,
            payment_note text DEFAULT NULL,
            system_ip varchar(50) DEFAULT NULL,
            system_name varchar(50) DEFAULT NULL,
            created_time time DEFAULT NULL,
            created_date date DEFAULT NULL,
            created_by varchar(50) DEFAULT NULL,
            status int(1) DEFAULT 1,
            account_id int(5) DEFAULT NULL,
            reference_no varchar(50) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_supplierpayments);

        // 60. Stock Adjustment Table
        $table_stockadjustment = $wpdb->prefix . 'orabooks_db_stockadjustment';
        $sql_stockadjustment = "CREATE TABLE $table_stockadjustment (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `warehouse_id` int(5) DEFAULT NULL,
            `reference_no` varchar(50) DEFAULT NULL,
            `adjustment_date` date DEFAULT NULL,
            `adjustment_note` mediumtext DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(50) DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `system_ip` varchar(100) DEFAULT NULL,
            `system_name` varchar(100) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_stockadjustment);

        // 61. Stock Adjustment Items Table
        $table_stockadjustmentitems = $wpdb->prefix . 'orabooks_db_stockadjustmentitems';
        $sql_stockadjustmentitems = "CREATE TABLE $table_stockadjustmentitems (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `warehouse_id` int(5) DEFAULT NULL,
            `adjustment_id` int(5) DEFAULT NULL,
            `item_id` int(5) DEFAULT NULL,
            `adjustment_qty` double(20,2) DEFAULT NULL,
            `status` int(5) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `account_id` int(11) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_stockadjustmentitems);

        // 63. Stock Transfer Table
        $table_stocktransfer = $wpdb->prefix . 'orabooks_db_stocktransfer';
        $sql_stocktransfer = "CREATE TABLE $table_stocktransfer (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `to_store_id` int(11) DEFAULT NULL,
            `warehouse_from` int(5) DEFAULT NULL,
            `warehouse_to` int(5) DEFAULT NULL,
            `transfer_date` date DEFAULT NULL,
            `note` text DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(50) DEFAULT NULL,
            `system_ip` varchar(50) DEFAULT NULL,
            `system_name` varchar(50) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_stocktransfer);

        // Stock Transfer Items
        $table_stock_transfer_items = $wpdb->prefix . 'orabooks_db_stocktransferitems';
        $sql_stock_transfer_items = "CREATE TABLE $table_stock_transfer_items (
            id int(11) NOT NULL AUTO_INCREMENT,
            stocktransfer_id int(11) NOT NULL,
            store_id int(11) DEFAULT NULL,
            warehouse_from int(11) DEFAULT NULL,
            warehouse_to int(11) DEFAULT NULL,
            item_id int(11) NOT NULL,
            transfer_qty double(20,2) NOT NULL,
            status int(1) DEFAULT 1,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_stock_transfer_items);

        // Sidebar Menu Table
        $table_sidebar = $wpdb->prefix . 'orabooks_db_sidebar';
        $sql_sidebar = "CREATE TABLE $table_sidebar (
            id int(11) NOT NULL AUTO_INCREMENT,
            module varchar(100) DEFAULT NULL,
            parent int(11) DEFAULT 0,
            menu_title varchar(100) NOT NULL,
            menu_slug varchar(100) NOT NULL,
            icon varchar(100) DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            status int(1) DEFAULT 1,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            created_by varchar(100) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_sidebar);

        // Warehouse Items Table
        $table_warehouse_items = $wpdb->prefix . 'orabooks_db_warehouseitems';
        $sql_warehouse_items = "CREATE TABLE $table_warehouse_items (
            id int(11) NOT NULL AUTO_INCREMENT,
            store_id int(11) DEFAULT NULL,
            warehouse_id int(11) DEFAULT NULL,
            item_id int(11) DEFAULT NULL,
            available_qty double(20,2) DEFAULT 0.00,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_warehouse_items);

        // 63. User Permissions Table
        $table_permissions = $wpdb->prefix . 'orabooks_user_permissions';
        $sql_permissions = "CREATE TABLE $table_permissions (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            sidebar_ids text DEFAULT NULL,
            status int(1) DEFAULT 1,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_permissions);

        // Roles Table
        $table_roles = $wpdb->prefix . 'orabooks_db_roles';
        $sql_roles = "CREATE TABLE $table_roles (
            id int(11) NOT NULL AUTO_INCREMENT,
            store_id int(11) DEFAULT NULL,
            role_name varchar(100) NOT NULL,
            description text DEFAULT NULL,
            status int(1) DEFAULT 1,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by varchar(100) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY store_id (store_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_roles);

        // Employees Table
        $table_employees = $wpdb->prefix . 'orabooks_db_employees';
        $sql_employees = "CREATE TABLE $table_employees (
            id int(11) NOT NULL AUTO_INCREMENT,
            store_id int(11) DEFAULT NULL,
            role_id int(11) DEFAULT NULL,
            employee_code varchar(50) DEFAULT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) DEFAULT NULL,
            email varchar(100) DEFAULT NULL,
            mobile varchar(20) DEFAULT NULL,
            phone varchar(20) DEFAULT NULL,
            address text DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            state varchar(100) DEFAULT NULL,
            postcode varchar(20) DEFAULT NULL,
            country varchar(100) DEFAULT NULL,
            hire_date date DEFAULT NULL,
            salary decimal(15,2) DEFAULT NULL,
            username varchar(50) DEFAULT NULL,
            password varchar(255) DEFAULT NULL,
            status int(1) DEFAULT 1,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by varchar(100) DEFAULT NULL,
            system_ip varchar(50) DEFAULT NULL,
            system_name varchar(100) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY store_id (store_id),
            KEY role_id (role_id),
            KEY status (status),
            KEY email (email),
            KEY employee_code (employee_code),
            KEY username (username)
        ) $charset_collate;";
        dbDelta($sql_employees);

        // Pre-populate Sidebar Table - Row by Row to Avoid Duplicates
        $default_menus = [
            ['module' => 'inventory', 'parent' => 0, 'menu_title' => 'Dashboard', 'menu_slug' => 'dashboard', 'icon' => 'fa-solid fa-gauge', 'sort_order' => 1, 'status' => 1],
            ['module' => 'inventory', 'parent' => 0, 'menu_title' => 'All Features', 'menu_slug' => 'all-features', 'icon' => 'fa-solid fa-star', 'sort_order' => 2, 'status' => 1],
            ['module' => 'inventory', 'parent' => 0, 'menu_title' => 'Settings', 'menu_slug' => 'settings', 'icon' => 'fa-solid fa-gear', 'sort_order' => 3, 'status' => 1],
            ['module' => 'inventory', 'parent' => 0, 'menu_title' => 'Employee', 'menu_slug' => 'employee', 'icon' => 'fa-solid fa-users', 'sort_order' => 4, 'status' => 1],
            ['module' => 'inventory', 'parent' => 0, 'menu_title' => 'Items', 'menu_slug' => 'items', 'icon' => 'fa-solid fa-sitemap', 'sort_order' => 5, 'status' => 1],
            ['module' => 'inventory', 'parent' => 0, 'menu_title' => 'Contact', 'menu_slug' => 'contact', 'icon' => 'fa-solid fa-address-book', 'sort_order' => 6, 'status' => 1],
            ['module' => 'inventory', 'parent' => 0, 'menu_title' => 'Sales', 'menu_slug' => 'sales', 'icon' => 'fa-solid fa-cart-shopping', 'sort_order' => 7, 'status' => 1],
            ['module' => 'inventory', 'parent' => 0, 'menu_title' => 'Purchase', 'menu_slug' => 'purchase', 'icon' => 'fa-solid fa-bag-shopping', 'sort_order' => 8, 'status' => 1],
            ['module' => 'inventory', 'parent' => 0, 'menu_title' => 'Stock', 'menu_slug' => 'stock', 'icon' => 'fa-solid fa-warehouse', 'sort_order' => 9, 'status' => 1],
            ['module' => 'inventory', 'parent' => 0, 'menu_title' => 'Reports', 'menu_slug' => 'reports', 'icon' => 'fa-solid fa-chart-line', 'sort_order' => 10, 'status' => 1],
        ];

        foreach ($default_menus as $menu) {
            $existing_parent = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table_sidebar WHERE module = %s AND menu_slug = %s AND parent = 0", 'inventory', $menu['menu_slug']));

            if (!$existing_parent) {
                $wpdb->insert($table_sidebar, $menu);
                $parent_id = $wpdb->insert_id;
            } else {
                $parent_id = $existing_parent->id;
            }

            // Submenus Data
            $submenus = [];
            if ($menu['menu_title'] == 'Settings') {
                $submenus = [
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Store Profile', 'menu_slug' => 'store-profile', 'icon' => 'fa-solid fa-store', 'sort_order' => 1, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Warehouse', 'menu_slug' => 'warehouse', 'icon' => 'fa-solid fa-warehouse', 'sort_order' => 2, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Database Backup', 'menu_slug' => 'db-backup', 'icon' => 'fa-solid fa-database', 'sort_order' => 5, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Add Features', 'menu_slug' => 'add-features', 'icon' => 'fa-solid fa-plus-circle', 'sort_order' => 6, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'User Permissions', 'menu_slug' => 'user-permissions', 'icon' => 'fa-solid fa-user-shield', 'sort_order' => 7, 'status' => 1],
                ];
            } elseif ($menu['menu_title'] == 'Employee') {
                $submenus = [
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Roles', 'menu_slug' => 'roles', 'icon' => 'fa-solid fa-user-tag', 'sort_order' => 1, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'View Employees', 'menu_slug' => 'view-employees', 'icon' => 'fa-solid fa-users', 'sort_order' => 2, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Add Employee', 'menu_slug' => 'add-employee', 'icon' => 'fa-solid fa-user-plus', 'sort_order' => 3, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Edit Employee', 'menu_slug' => 'edit-employee', 'icon' => 'fa-solid fa-user-pen', 'sort_order' => 4, 'status' => 1],
                ];
            } elseif ($menu['menu_title'] == 'Items') {
                $submenus = [
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'View Items', 'menu_slug' => 'view-items', 'icon' => 'fa-solid fa-list', 'sort_order' => 1, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Add Item', 'menu_slug' => 'add-item', 'icon' => 'fa-solid fa-plus', 'sort_order' => 2, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Edit Item', 'menu_slug' => 'edit-item', 'icon' => 'fa-solid fa-pen-to-square', 'sort_order' => 3, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Import Items', 'menu_slug' => 'import-items', 'icon' => 'fa-solid fa-file-import', 'sort_order' => 4, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Add Service', 'menu_slug' => 'add-service', 'icon' => 'fa-solid fa-concierge-bell', 'sort_order' => 5, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Categories', 'menu_slug' => 'categories', 'icon' => 'fa-solid fa-tags', 'sort_order' => 6, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Brands', 'menu_slug' => 'brands', 'icon' => 'fa-solid fa-copyright', 'sort_order' => 7, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Units', 'menu_slug' => 'units', 'icon' => 'fa-solid fa-ruler', 'sort_order' => 8, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Variants List', 'menu_slug' => 'variants-list', 'icon' => 'fa-solid fa-layer-group', 'sort_order' => 9, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Print Labels', 'menu_slug' => 'print-labels', 'icon' => 'fa-solid fa-print', 'sort_order' => 10, 'status' => 1],
                ];
            } elseif ($menu['menu_title'] == 'Contact') {
                $submenus = [
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Customers', 'menu_slug' => 'customers', 'icon' => 'fa-solid fa-user-group', 'sort_order' => 1, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Suppliers', 'menu_slug' => 'suppliers', 'icon' => 'fa-solid fa-truck-field', 'sort_order' => 4, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Import Customers', 'menu_slug' => 'import-customers', 'icon' => 'fa-solid fa-file-import', 'sort_order' => 3, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Import Suppliers', 'menu_slug' => 'import-suppliers', 'icon' => 'fa-solid fa-file-import', 'sort_order' => 5, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Customer Pay', 'menu_slug' => 'customer-pay', 'icon' => 'fa-solid fa-money-bill-wave', 'sort_order' => 2, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Supplier Pay', 'menu_slug' => 'supplier-pay', 'icon' => 'fa-solid fa-money-bill-transfer', 'sort_order' => 6, 'status' => 1],
                ];
            } elseif ($menu['menu_title'] == 'Sales') {
                $submenus = [
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'View Sales', 'menu_slug' => 'view-sales', 'icon' => 'fa-solid fa-file-invoice', 'sort_order' => 1, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Add Sale', 'menu_slug' => 'add-sale', 'icon' => 'fa-solid fa-cart-plus', 'sort_order' => 2, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'POS Sale', 'menu_slug' => 'pos-sale', 'icon' => 'fa-solid fa-cash-register', 'sort_order' => 3, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Sales Orders', 'menu_slug' => 'sales-order-list', 'icon' => 'fa-solid fa-clipboard-list', 'sort_order' => 4, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Pending Delivery', 'menu_slug' => 'sales-pending-delivery', 'icon' => 'fa-solid fa-clock', 'sort_order' => 5, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Sales Invoice', 'menu_slug' => 'sales-invoice', 'icon' => 'fa-solid fa-file-invoice', 'sort_order' => 6, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Edit Sales', 'menu_slug' => 'edit-sales', 'icon' => 'fa-solid fa-pen-to-square', 'sort_order' => 7, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Sales Return List', 'menu_slug' => 'sales-return-list', 'icon' => 'fa-solid fa-arrow-rotate-left', 'sort_order' => 8, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Add Sales Return', 'menu_slug' => 'add-sales-return', 'icon' => 'fa-solid fa-plus', 'sort_order' => 9, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Edit Sales Return', 'menu_slug' => 'edit-sales-return', 'icon' => 'fa-solid fa-pen-to-square', 'sort_order' => 10, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Sales Return Invoice', 'menu_slug' => 'sales-return-invoice', 'icon' => 'fa-solid fa-file-invoice', 'sort_order' => 11, 'status' => 1],
                ];
            } elseif ($menu['menu_title'] == 'Purchase') {
                $submenus = [
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'View Purchase', 'menu_slug' => 'view-purchase', 'icon' => 'fa-solid fa-file-invoice-dollar', 'sort_order' => 1, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Add Purchase', 'menu_slug' => 'add-purchase', 'icon' => 'fa-solid fa-bag-shopping', 'sort_order' => 2, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Purchase Orders', 'menu_slug' => 'purchase-ordered-list', 'icon' => 'fa-solid fa-clipboard-list', 'sort_order' => 3, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Pending Purchases', 'menu_slug' => 'purchase-pending-list', 'icon' => 'fa-solid fa-clock', 'sort_order' => 4, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Purchase Invoice', 'menu_slug' => 'purchase-invoice', 'icon' => 'fa-solid fa-file-invoice', 'sort_order' => 5, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Edit Purchase', 'menu_slug' => 'edit-purchase', 'icon' => 'fa-solid fa-pen-to-square', 'sort_order' => 6, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Purchase Return List', 'menu_slug' => 'purchase-return-list', 'icon' => 'fa-solid fa-arrow-rotate-left', 'sort_order' => 7, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Add Purchase Return', 'menu_slug' => 'add-purchase-return', 'icon' => 'fa-solid fa-plus', 'sort_order' => 8, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Edit Purchase Return', 'menu_slug' => 'edit-purchase-return', 'icon' => 'fa-solid fa-pen-to-square', 'sort_order' => 9, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Purchase Return Invoice', 'menu_slug' => 'purchase-return-invoice', 'icon' => 'fa-solid fa-file-invoice', 'sort_order' => 10, 'status' => 1],
                ];
            } elseif ($menu['menu_title'] == 'Stock') {
                $submenus = [
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Adjustment List', 'menu_slug' => 'adjustment-list', 'icon' => 'fa-solid fa-sliders', 'sort_order' => 1, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Add Adjustment', 'menu_slug' => 'add-adjustment', 'icon' => 'fa-solid fa-plus', 'sort_order' => 2, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Edit Adjustment', 'menu_slug' => 'edit-adjustment', 'icon' => 'fa-solid fa-pen-to-square', 'sort_order' => 3, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Adjustment Invoice', 'menu_slug' => 'adjustment-invoice', 'icon' => 'fa-solid fa-file-invoice', 'sort_order' => 4, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Transfer List', 'menu_slug' => 'transfer-list', 'icon' => 'fa-solid fa-right-left', 'sort_order' => 5, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Add Transfer', 'menu_slug' => 'add-transfer', 'icon' => 'fa-solid fa-plus', 'sort_order' => 6, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Edit Transfer', 'menu_slug' => 'edit-transfer', 'icon' => 'fa-solid fa-pen-to-square', 'sort_order' => 7, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Transfer Invoice', 'menu_slug' => 'transfer-invoice', 'icon' => 'fa-solid fa-file-invoice', 'sort_order' => 8, 'status' => 1],
                ];
            } elseif ($menu['menu_title'] == 'Reports') {
                $submenus = [
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Journal Report', 'menu_slug' => 'journal-report', 'icon' => 'fa-solid fa-book', 'sort_order' => 1, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Ledger Report', 'menu_slug' => 'ledger-report', 'icon' => 'fa-solid fa-book-open', 'sort_order' => 2, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Trial Balance', 'menu_slug' => 'trial-balance-report', 'icon' => 'fa-solid fa-scale-unbalanced', 'sort_order' => 3, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Income Statement', 'menu_slug' => 'income-statement-report', 'icon' => 'fa-solid fa-file-invoice', 'sort_order' => 4, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Balance Sheet', 'menu_slug' => 'balance-sheet-report', 'icon' => 'fa-solid fa-file-invoice-dollar', 'sort_order' => 5, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'All Reports', 'menu_slug' => 'all-reports', 'icon' => 'fa-solid fa-list-check', 'sort_order' => 6, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Sales Report', 'menu_slug' => 'sales-report', 'icon' => 'fa-solid fa-chart-bar', 'sort_order' => 7, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Purchase Report', 'menu_slug' => 'purchase-report', 'icon' => 'fa-solid fa-chart-pie', 'sort_order' => 8, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Stock Report', 'menu_slug' => 'stock-report', 'icon' => 'fa-solid fa-boxes-stacked', 'sort_order' => 9, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Profit Loss Report', 'menu_slug' => 'profit-loss-report', 'icon' => 'fa-solid fa-scale-balanced', 'sort_order' => 10, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Customer Due', 'menu_slug' => 'customer-due-report', 'icon' => 'fa-solid fa-user-clock', 'sort_order' => 11, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Sales Payment', 'menu_slug' => 'sales-payment-report', 'icon' => 'fa-solid fa-money-bill-wave', 'sort_order' => 12, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Customer Payment', 'menu_slug' => 'customer-payment-report', 'icon' => 'fa-solid fa-money-check-dollar', 'sort_order' => 13, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Supplier Payment', 'menu_slug' => 'supplier-payment-report', 'icon' => 'fa-solid fa-money-check-dollar', 'sort_order' => 14, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Supplier Due', 'menu_slug' => 'supplier-due-report', 'icon' => 'fa-solid fa-user-tag', 'sort_order' => 15, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Sales Summary', 'menu_slug' => 'sales-summary-report', 'icon' => 'fa-solid fa-chart-line', 'sort_order' => 16, 'status' => 1],
                    ['module' => 'inventory', 'parent' => $parent_id, 'menu_title' => 'Stock Transfer', 'menu_slug' => 'stock-transfer-report', 'icon' => 'fa-solid fa-truck-moving', 'sort_order' => 17, 'status' => 1],
                ];
            }

            foreach ($submenus as $submenu) {
                $exists = $wpdb->get_var($wpdb->prepare("SELECT count(*) FROM $table_sidebar WHERE module = %s AND menu_slug = %s AND parent = %d", 'inventory', $submenu['menu_slug'], $parent_id));
                if (!$exists) {
                    $wpdb->insert($table_sidebar, $submenu);
                } else {
                    // Update sort_order for existing reports to match new order
                    $wpdb->update($table_sidebar, ['sort_order' => $submenu['sort_order']], ['module' => 'inventory', 'menu_slug' => $submenu['menu_slug'], 'parent' => $parent_id]);
                }
            }
        }

        // Initialize Permissions for Admin Users
        $table_permissions = $wpdb->prefix . 'orabooks_user_permissions';

        // Fetch all inventory menu IDs from sidebar table
        $inventory_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table_sidebar WHERE module = %s", 'inventory'));

        if (!empty($inventory_ids)) {
            // Get all administrator user IDs
            $admin_ids = $wpdb->get_col("SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '{$wpdb->prefix}capabilities' AND meta_value LIKE '%\"administrator\"%'");

            // If no admins found via meta (e.g. multisite primary admin), try user ID 1
            if (empty($admin_ids)) {
                $admin_ids = [1];
            }

            foreach ($admin_ids as $admin_id) {
                $existing_permission = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_permissions WHERE user_id = %d", $admin_id));
                $permissions_json = json_encode(array_map('intval', array_values($inventory_ids)));

                if (!$existing_permission) {
                    $wpdb->insert($table_permissions, array(
                        'user_id' => $admin_id,
                        'sidebar_ids' => $permissions_json
                    ));
                }
            }
        }

        dbDelta($sql);
    }

}
