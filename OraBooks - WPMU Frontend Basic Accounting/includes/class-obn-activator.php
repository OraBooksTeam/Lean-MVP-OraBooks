<?php

class OBN_Activator
{

    public static function activate()
    {
        global $wpdb;
        ob_start();
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // 1 Table: orabooks_db_users
        $table1 = $wpdb->prefix . 'orabooks_db_users';
        $sql1 = "CREATE TABLE {$table1} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `username` varchar(50) DEFAULT NULL,
            `first_name` varchar(100) DEFAULT NULL,
            `last_name` varchar(100) DEFAULT NULL,
            `password` blob DEFAULT NULL,
            `member_of` varchar(50) DEFAULT NULL,
            `firstname` varchar(50) DEFAULT NULL,
            `lastname` varchar(50) DEFAULT NULL,
            `mobile` varchar(50) DEFAULT NULL,
            `email` varchar(50) DEFAULT NULL,
            `photo` blob DEFAULT NULL,
            `gender` varchar(50) DEFAULT NULL,
            `dob` date DEFAULT NULL,
            `country` varchar(50) DEFAULT NULL,
            `state` varchar(50) DEFAULT NULL,
            `city` varchar(50) DEFAULT NULL,
            `address` blob DEFAULT NULL,
            `postcode` varchar(50) DEFAULT NULL,
            `role_name` varchar(50) DEFAULT NULL,
            `role_id` int(5) DEFAULT NULL,
            `profile_picture` text DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(50) DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `system_ip` varchar(100) DEFAULT NULL,
            `system_name` varchar(100) DEFAULT NULL,
            `status` double DEFAULT NULL,
            `creater_id` int(5) DEFAULT NULL,
            `updater_id` int(5) DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            `default_warehouse_id` int(11) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 41 Table: orabooks_db_store (New Store Configuration)
        $table_store_init = $wpdb->prefix . 'orabooks_db_store';
        $sql_store_init = "CREATE TABLE {$table_store_init} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `store_code` varchar(50) DEFAULT NULL,
            `store_name` varchar(150) DEFAULT NULL,
            `mobile` varchar(50) DEFAULT NULL,
            `email` varchar(100) DEFAULT NULL,
            `phone` varchar(50) DEFAULT NULL,
            `gst_no` varchar(50) DEFAULT NULL,
            `tax_number` varchar(50) DEFAULT NULL,
            `pan_no` varchar(50) DEFAULT NULL,
            `store_website` varchar(150) DEFAULT NULL,
            `show_signature` int(1) DEFAULT 0,
            `signature_image` text DEFAULT NULL,
            `bank_details` mediumtext DEFAULT NULL,
            `country` varchar(100) DEFAULT NULL,
            `state` varchar(100) DEFAULT NULL,
            `city` varchar(100) DEFAULT NULL,
            `postcode` varchar(50) DEFAULT NULL,
            `address` text DEFAULT NULL,
            `store_logo` text DEFAULT NULL,
            
            /* System Tab */
            `timezone` varchar(100) DEFAULT NULL,
            `date_format` varchar(50) DEFAULT NULL,
            `time_format` varchar(50) DEFAULT NULL,
            `currency_id` int(5) DEFAULT NULL,
            `currency_placement` varchar(50) DEFAULT NULL,
            `decimals` int(2) DEFAULT NULL,
            `qty_decimals` int(2) DEFAULT NULL,
            `language` varchar(50) DEFAULT NULL,
            `round_off` int(1) DEFAULT 0,
            
            
            /* Sales Tab */
            `default_account_id` int(11) DEFAULT NULL,
            `default_sales_discount` double(20,2) DEFAULT NULL,
            `sales_invoice_format` varchar(50) DEFAULT NULL,
            `pos_invoice_format` varchar(50) DEFAULT NULL,
            `show_mrp_pos` int(1) DEFAULT 0,
            `show_paid_change_pos` int(1) DEFAULT 0,
            `show_prev_balance` int(1) DEFAULT 0,
            `num_to_words_format` varchar(50) DEFAULT NULL,
            `invoice_terms` text DEFAULT NULL,
            `sales_description` text DEFAULT NULL,
            
            /* Prefixes Tab */
            `category_init` varchar(20) DEFAULT NULL,
            `supplier_init` varchar(20) DEFAULT NULL,
            `purchase_return_init` varchar(20) DEFAULT NULL,
            `sales_init` varchar(20) DEFAULT NULL,
            `expense_init` varchar(20) DEFAULT NULL,
            `quotation_init` varchar(20) DEFAULT NULL,
            `sales_payment_init` varchar(20) DEFAULT NULL,
            `purchase_payment_init` varchar(20) DEFAULT NULL,
            `expense_payment_init` varchar(20) DEFAULT NULL,
            `item_init` varchar(20) DEFAULT NULL,
            `purchase_init` varchar(20) DEFAULT NULL,
            `customer_init` varchar(20) DEFAULT NULL,
            `sales_return_init` varchar(20) DEFAULT NULL,
            `accounts_init` varchar(20) DEFAULT NULL,
            `money_transfer_init` varchar(20) DEFAULT NULL,
            `sales_return_payment_init` varchar(20) DEFAULT NULL,
            `purchase_return_payment_init` varchar(20) DEFAULT NULL,
            `cust_advance_init` varchar(20) DEFAULT NULL,

            `created_date` date DEFAULT NULL,
            `created_time` varchar(50) DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            `status` int(1) DEFAULT 1,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 2 Table: orabooks_db_userswarehouses
        $table2 = $wpdb->prefix . 'orabooks_db_userswarehouses';
        $sql2 = "CREATE TABLE {$table2} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `user_id` int(5) DEFAULT NULL,
            `warehouse_id` int(5) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 3 Table: orabooks_db_variants
        $table3 = $wpdb->prefix . 'orabooks_db_variants';
        $sql3 = "CREATE TABLE {$table3} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `variant_code` varchar(50) DEFAULT NULL,
            `variant_name` varchar(100) DEFAULT NULL,
            `description` mediumtext DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 4 Table: orabooks_db_warehouse
        $table4 = $wpdb->prefix . 'orabooks_db_warehouse';
        $sql4 = "CREATE TABLE {$table4} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `warehouse_type` varchar(50) DEFAULT NULL,
            `warehouse_name` varchar(50) DEFAULT NULL,
            `mobile` varchar(20) DEFAULT NULL,
            `email` varchar(50) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 5 Table: orabooks_db_warehouseitems
        $table5 = $wpdb->prefix . 'orabooks_db_warehouseitems';
        $sql5 = "CREATE TABLE {$table5} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `warehouse_id` int(5) DEFAULT NULL,
            `item_id` int(5) DEFAULT NULL,
            `available_qty` double(20,2) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 6 Table: orabooks_temp_holdinvoice
        $table6 = $wpdb->prefix . 'orabooks_temp_holdinvoice';
        $sql6 = "CREATE TABLE {$table6} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `invoice_id` int(5) DEFAULT NULL,
            `invoice_date` date DEFAULT NULL,
            `reference_id` varchar(50) DEFAULT NULL,
            `item_id` int(5) DEFAULT NULL,
            `item_qty` int(5) DEFAULT NULL,
            `item_price` double(10,2) DEFAULT NULL,
            `tax` double(10,2) DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(50) DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `system_ip` varchar(50) DEFAULT NULL,
            `system_name` varchar(50) DEFAULT NULL,
            `pos` int(5) DEFAULT NULL,
            `status` int(5) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 7 Table: orabooks_ac_accounts
        $table7 = $wpdb->prefix . 'orabooks_ac_accounts';
        $sql7 = "CREATE TABLE {$table7} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `count_id` int(5) DEFAULT NULL,
            `store_id` int(5) DEFAULT NULL,
            `parent_id` int(5) DEFAULT NULL,
            `sort_code` varchar(100) DEFAULT NULL,
            `account_name` varchar(50) DEFAULT NULL,
            `account_code` varchar(50) DEFAULT NULL,
            `balance` double(20,4) DEFAULT NULL,
            `note` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
            `created_by` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
            `system_ip` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
            `system_name` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            `delete_bit` int(1) DEFAULT 0,
            `account_selection_name` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
            `paymenttypes_id` int(1) DEFAULT NULL,
            `customer_id` int(5) DEFAULT NULL,
            `supplier_id` int(5) DEFAULT NULL,
            `expense_id` int(5) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 8 Table: orabooks_ac_moneydeposits
        $table8 = $wpdb->prefix . 'orabooks_ac_moneydeposits';
        $sql8 = "CREATE TABLE {$table8} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `deposit_date` date DEFAULT NULL,
            `reference_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `debit_account_id` int(11) DEFAULT NULL,
            `credit_account_id` int(11) DEFAULT NULL,
            `amount` double(20,4) DEFAULT NULL,
            `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `created_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `created_date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `created_time` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `system_ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `system_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 9 Table: orabooks_ac_moneytransfer
        $table9 = $wpdb->prefix . 'orabooks_ac_moneytransfer';
        $sql9 = "CREATE TABLE {$table9} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `count_id` int(10) DEFAULT NULL,
            `transfer_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `transfer_date` date DEFAULT NULL,
            `reference_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `debit_account_id` int(11) DEFAULT NULL,
            `credit_account_id` int(11) DEFAULT NULL,
            `amount` double(20,4) DEFAULT NULL,
            `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `created_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `created_date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `created_time` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `system_ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `system_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 10 Table: orabooks_ac_transactions
        $table10 = $wpdb->prefix . 'orabooks_ac_transactions';
        $sql10 = "CREATE TABLE {$table10} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `payment_code` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
            `transaction_date` date DEFAULT NULL,
            `transaction_type` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
            `debit_account_id` int(5) DEFAULT NULL,
            `credit_account_id` int(5) DEFAULT NULL,
            `debit_amt` double(20,4) DEFAULT NULL,
            `credit_amt` double(20,4) DEFAULT NULL,
            `note` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
            `created_by` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `ref_accounts_id` int(5) DEFAULT NULL COMMENT 'reference table',
            `ref_moneytransfer_id` int(5) DEFAULT NULL COMMENT 'reference table',
            `ref_moneydeposits_id` int(5) DEFAULT NULL COMMENT 'reference table',
            `ref_salespayments_id` int(5) DEFAULT NULL,
            `ref_salespaymentsreturn_id` int(5) DEFAULT NULL,
            `ref_purchasepayments_id` int(5) DEFAULT NULL,
            `ref_purchasepaymentsreturn_id` int(5) DEFAULT NULL,
            `ref_expense_id` int(5) DEFAULT NULL,
            `customer_id` int(5) DEFAULT NULL,
            `supplier_id` int(5) DEFAULT NULL,
            `short_code` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 11 Table: orabooks_ci_sessions
        $table11 = $wpdb->prefix . 'orabooks_ci_sessions';
        $sql11 = "CREATE TABLE {$table11} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `timestamp` int(10) UNSIGNED NOT NULL DEFAULT 0,
            `data` blob NOT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 12 Table: orabooks_db_bankdetails
        $table12 = $wpdb->prefix . 'orabooks_db_bankdetails';
        $sql12 = "CREATE TABLE {$table12} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `country_id` int(5) DEFAULT NULL,
            `holder_name` varchar(250) DEFAULT NULL,
            `bank_name` varchar(250) DEFAULT NULL,
            `branch_name` varchar(250) DEFAULT NULL,
            `code` varchar(250) DEFAULT NULL COMMENT 'IFSC or Bank Code',
            `account_type` varchar(250) DEFAULT NULL,
            `account_number` varchar(250) DEFAULT NULL,
            `other_details` mediumtext DEFAULT NULL,
            `description` mediumtext DEFAULT NULL,
            `status` int(5) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 13 Table: orabooks_db_brands
        $table13 = $wpdb->prefix . 'orabooks_db_brands';
        $sql13 = "CREATE TABLE {$table13} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `brand_code` varchar(50) DEFAULT NULL,
            `brand_name` varchar(100) DEFAULT NULL,
            `description` mediumtext DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 14 Table: orabooks_db_category
        $table14 = $wpdb->prefix . 'orabooks_db_category';
        $sql14 = "CREATE TABLE {$table14} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `count_id` int(10) DEFAULT NULL COMMENT 'Use to create category Code',
            `category_code` varchar(50) DEFAULT NULL,
            `category_name` varchar(100) DEFAULT NULL,
            `description` mediumtext DEFAULT NULL,
            `company_id` int(5) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 15 Table: orabooks_db_cobpayments
        $table15 = $wpdb->prefix . 'orabooks_db_cobpayments';
        $sql15 = "CREATE TABLE {$table15} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `customer_id` int(5) DEFAULT NULL,
            `payment_date` date DEFAULT NULL,
            `payment_type` varchar(50) DEFAULT NULL,
            `payment` double(10,2) DEFAULT NULL,
            `payment_note` mediumtext DEFAULT NULL,
            `system_ip` varchar(50) DEFAULT NULL,
            `system_name` varchar(50) DEFAULT NULL,
            `created_time` time DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 16 Table: orabooks_db_company
        $table16 = $wpdb->prefix . 'orabooks_db_company';
        $sql16 = "CREATE TABLE {$table16} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `company_code` varchar(150) DEFAULT NULL,
            `company_name` varchar(150) DEFAULT NULL,
            `company_website` varchar(150) DEFAULT NULL,
            `mobile` varchar(150) DEFAULT NULL,
            `phone` varchar(150) DEFAULT NULL,
            `email` varchar(100) DEFAULT NULL,
            `website` varchar(250) DEFAULT NULL,
            `company_logo` text DEFAULT NULL,
            `logo` mediumtext DEFAULT NULL,
            `upi_id` varchar(50) DEFAULT NULL,
            `upi_code` text DEFAULT NULL,
            `country` varchar(150) DEFAULT NULL,
            `state` varchar(150) DEFAULT NULL,
            `city` varchar(100) DEFAULT NULL,
            `address` varchar(300) DEFAULT NULL,
            `postcode` varchar(50) DEFAULT NULL,
            `gst_no` varchar(50) DEFAULT NULL,
            `vat_no` varchar(50) DEFAULT NULL,
            `pan_no` varchar(50) DEFAULT NULL,
            `bank_details` mediumtext DEFAULT NULL,
            `cid` int(10) DEFAULT NULL,
            `category_init` varchar(5) DEFAULT NULL,
            `item_init` varchar(5) DEFAULT NULL COMMENT 'INITAL CODE',
            `supplier_init` varchar(5) DEFAULT NULL COMMENT 'INITAL CODE',
            `purchase_init` varchar(5) DEFAULT NULL COMMENT 'INITAL CODE',
            `purchase_return_init` varchar(5) DEFAULT NULL,
            `customer_init` varchar(5) DEFAULT NULL COMMENT 'INITAL CODE',
            `sales_init` varchar(5) DEFAULT NULL COMMENT 'INITAL CODE',
            `sales_return_init` varchar(5) DEFAULT NULL,
            `expense_init` varchar(5) DEFAULT NULL,
            `invoice_view` int(5) DEFAULT NULL COMMENT '1=Standard,2=Indian GST',
            `status` int(1) DEFAULT NULL,
            `sms_status` int(1) DEFAULT NULL COMMENT '1=Enable 0=Disable',
            `sales_terms_and_conditions` text DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 17 Table: orabooks_db_country
        $table17 = $wpdb->prefix . 'orabooks_db_country';
        $sql17 = "CREATE TABLE {$table17} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `country` varchar(50) DEFAULT NULL,
            `added_on` date DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 18 Table: orabooks_db_coupons
        $table18 = $wpdb->prefix . 'orabooks_db_coupons';
        $sql18 = "CREATE TABLE {$table18} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `code` varchar(50) DEFAULT NULL,
            `name` varchar(100) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `value` double(20,2) DEFAULT NULL,
            `type` varchar(50) DEFAULT NULL,
            `expire_date` date DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            `created_by` varchar(100) DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(100) DEFAULT NULL,
            `system_name` varchar(250) DEFAULT NULL,
            `system_ip` varchar(250) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 19 Table: orabooks_db_currency
        $table19 = $wpdb->prefix . 'orabooks_db_currency';
        $sql19 = "CREATE TABLE {$table19} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `currency_name` varchar(50) DEFAULT NULL,
            `currency_code` varchar(20) DEFAULT NULL,
            `currency` blob DEFAULT NULL,
            `symbol` mediumtext DEFAULT NULL,
            `status` int(5) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 20 Table: orabooks_db_custadvance
        $table20 = $wpdb->prefix . 'orabooks_db_custadvance';
        $sql20 = "CREATE TABLE {$table20} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `count_id` int(5) DEFAULT NULL,
            `payment_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `payment_date` date DEFAULT NULL,
            `customer_id` int(5) DEFAULT NULL,
            `amount` double(20,4) DEFAULT NULL,
            `payment_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `created_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `system_ip` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `system_name` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 21 Table: orabooks_db_customers
        $table21 = $wpdb->prefix . 'orabooks_db_customers';
        $sql21 = "CREATE TABLE {$table21} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `count_id` int(10) DEFAULT NULL COMMENT 'Use to create Customer Code',
            `customer_code` varchar(20) DEFAULT NULL,
            `customer_name` varchar(50) DEFAULT NULL,
            `mobile` varchar(15) DEFAULT NULL,
            `phone` varchar(15) DEFAULT NULL,
            `email` varchar(50) DEFAULT NULL,
            `gstin` varchar(100) DEFAULT NULL,
            `tax_number` varchar(50) DEFAULT NULL,
            `vatin` varchar(100) DEFAULT NULL,
            `opening_balance` double(20,4) DEFAULT NULL,
            `sales_due` double(20,4) DEFAULT NULL,
            `sales_return_due` double(20,4) DEFAULT NULL,
            `country_id` varchar(50) DEFAULT NULL,
            `state_id` varchar(50) DEFAULT NULL,
            `city` varchar(50) DEFAULT NULL,
            `postcode` varchar(10) DEFAULT NULL,
            `address` varchar(250) DEFAULT NULL,
            `ship_country_id` varchar(50) DEFAULT NULL,
            `ship_state_id` varchar(50) DEFAULT NULL,
            `ship_city` varchar(100) DEFAULT NULL,
            `ship_postcode` varchar(10) DEFAULT NULL,
            `ship_address` text DEFAULT NULL,
            `system_ip` varchar(50) DEFAULT NULL,
            `system_name` varchar(50) DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(30) DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `company_id` int(5) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            `location_link` text DEFAULT NULL,
            `attachment_1` text DEFAULT NULL,
            `price_level_type` varchar(50) DEFAULT 'Increase',
            `price_level` double(20,4) DEFAULT 0.0000,
            `delete_bit` int(1) DEFAULT 0,
            `tot_advance` double(20,4) DEFAULT NULL,
            `credit_limit` double(20,4) DEFAULT -1.0000,
            `shippingaddress_id` int(10) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 22 Table: orabooks_db_customer_coupons
        $table22 = $wpdb->prefix . 'orabooks_db_customer_coupons';
        $sql22 = "CREATE TABLE {$table22} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `code` varchar(50) DEFAULT NULL,
            `name` varchar(100) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `value` double(20,2) DEFAULT NULL,
            `type` varchar(50) DEFAULT NULL,
            `expire_date` date DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            `created_by` varchar(100) DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(100) DEFAULT NULL,
            `system_name` varchar(250) DEFAULT NULL,
            `system_ip` varchar(250) DEFAULT NULL,
            `customer_id` int(10) DEFAULT NULL,
            `coupon_id` int(10) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 23 Table: orabooks_db_customer_payments
        $table23 = $wpdb->prefix . 'orabooks_db_customer_payments';
        $sql23 = "CREATE TABLE {$table23} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `salespayment_id` int(5) DEFAULT NULL,
            `customer_id` int(5) DEFAULT NULL,
            `payment_date` date DEFAULT NULL,
            `payment_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `payment` double(10,2) DEFAULT NULL,
            `payment_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `system_ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `system_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `created_time` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `created_date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `created_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 24 Table: orabooks_db_emailtemplates
        $table24 = $wpdb->prefix . 'orabooks_db_emailtemplates';
        $sql24 = "CREATE TABLE {$table24} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `template_key` varchar(150) DEFAULT NULL,
            `template_name` varchar(100) DEFAULT NULL,
            `content` text DEFAULT NULL,
            `variables` text DEFAULT NULL,
            `status` int(5) DEFAULT NULL,
            `undelete_bit` int(5) DEFAULT NULL,
            `admin_only` int(1) DEFAULT 0,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 25 Table: orabooks_db_expense
        $table25 = $wpdb->prefix . 'orabooks_db_expense';
        $sql25 = "CREATE TABLE {$table25} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `count_id` int(10) DEFAULT NULL COMMENT 'Use to create Expense Code',
            `expense_code` varchar(50) DEFAULT NULL,
            `expense_date` date DEFAULT NULL,
            `reference_no` varchar(50) DEFAULT NULL,
            `supplier_id` int(5) DEFAULT NULL,
            `payment_type` varchar(100) DEFAULT NULL,
            `account_id` int(5) DEFAULT NULL,
            `billing_address` text DEFAULT NULL,
            `total_amount` double(20,4) DEFAULT NULL,
            `paid_amount` double(20,4) DEFAULT 0.0000,
            `due_amount` double(20,4) DEFAULT 0.0000,
            `payment_status` varchar(20) DEFAULT 'Paid',
            `comments` text DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(50) DEFAULT NULL,
            `system_ip` varchar(50) DEFAULT NULL,
            `system_name` varchar(50) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 26 Table: orabooks_db_expense_category
        $table26 = $wpdb->prefix . 'orabooks_db_expense_category';
        $sql26 = "CREATE TABLE {$table26} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `category_code` varchar(50) DEFAULT NULL,
            `category_name` varchar(50) DEFAULT NULL,
            `description` mediumtext DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 27 Table: orabooks_db_expense_items
        $table27 = $wpdb->prefix . 'orabooks_db_expense_items';
        $sql27 = "CREATE TABLE {$table27} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `expense_id` int(5) DEFAULT NULL,
            `account_id` int(5) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `amount` double(20,4) DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(50) DEFAULT NULL,
            `system_ip` varchar(50) DEFAULT NULL,
            `system_name` varchar(50) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY `expense_id` (`expense_id`),
            KEY `account_id` (`account_id`)
        ) ENGINE=InnoDB {$charset_collate};";

        // 27b Table: orabooks_db_fivemojo
        $table_fivemojo = $wpdb->prefix . 'orabooks_db_fivemojo';
        $sql_fivemojo = "CREATE TABLE {$table_fivemojo} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `instance_id` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `status` int(1) DEFAULT 0,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};"
        . "\n";

        // 28 Table: orabooks_db_hold
        $table28 = $wpdb->prefix . 'orabooks_db_hold';
        $sql28 = "CREATE TABLE {$table28} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `warehouse_id` int(5) DEFAULT NULL,
            `reference_id` varchar(50) DEFAULT NULL COMMENT 'Temprary',
            `reference_no` varchar(50) DEFAULT NULL,
            `sales_date` date DEFAULT NULL,
            `sales_status` varchar(50) DEFAULT NULL,
            `customer_id` int(5) DEFAULT NULL,
            `other_charges_input` double(20,2) DEFAULT NULL,
            `other_charges_tax_id` int(5) DEFAULT NULL,
            `other_charges_amt` double(20,2) DEFAULT NULL,
            `discount_to_all_input` double(20,2) DEFAULT NULL,
            `discount_to_all_type` varchar(50) DEFAULT NULL,
            `tot_discount_to_all_amt` double(20,2) DEFAULT NULL,
            `subtotal` double(20,2) DEFAULT NULL,
            `round_off` double(20,2) DEFAULT NULL,
            `grand_total` double(20,4) DEFAULT NULL,
            `sales_note` text DEFAULT NULL,
            `pos` int(1) DEFAULT NULL COMMENT '1=yes 0=no',
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 29 Table: orabooks_db_holditems
        $table29 = $wpdb->prefix . 'orabooks_db_holditems';
        $sql29 = "CREATE TABLE {$table29} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `hold_id` int(5) DEFAULT NULL,
            `item_id` int(5) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `sales_qty` double(20,2) DEFAULT NULL,
            `price_per_unit` double(20,4) DEFAULT NULL,
            `tax_type` varchar(50) DEFAULT NULL,
            `tax_id` int(5) DEFAULT NULL,
            `tax_amt` double(20,4) DEFAULT NULL,
            `discount_type` varchar(50) DEFAULT NULL,
            `discount_input` double(20,4) DEFAULT NULL,
            `discount_amt` double(20,4) DEFAULT NULL,
            `unit_total_cost` double(20,4) DEFAULT NULL,
            `total_cost` double(20,4) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 30 Table: orabooks_db_instamojo
        $table30 = $wpdb->prefix . 'orabooks_db_instamojo';
        $sql30 = "CREATE TABLE {$table30} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `sandbox` int(1) DEFAULT NULL,
            `api_key` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `api_token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `updated_at` date DEFAULT NULL,
            `updated_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 31 Table: orabooks_db_instamojopayments
        $table31 = $wpdb->prefix . 'orabooks_db_instamojopayments';
        $sql31 = "CREATE TABLE {$table31} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `phone` varchar(25) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
            `email` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
            `buyer_name` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
            `amount` decimal(16,2) NOT NULL,
            `purpose` text CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
            `expires_at` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
            `status` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
            `send_sms` varchar(5) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'false',
            `send_email` varchar(5) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'false',
            `sms_status` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
            `email_status` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
            `shorturl` mediumtext CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
            `longurl` mediumtext CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
            `redirect_url` mediumtext CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
            `webhook` mediumtext CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
            `allow_repeated_payments` varchar(5) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'false',
            `customer_id` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
            `created_at` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
            `modified_at` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 32 Table: orabooks_db_items
        $table32 = $wpdb->prefix . 'orabooks_db_items';
        $sql32 = "CREATE TABLE {$table32} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `count_id` int(10) DEFAULT NULL COMMENT 'Use to create ITEM Code',
            `item_code` varchar(50) DEFAULT NULL,
            `item_name` varchar(100) DEFAULT NULL,
            `category_id` int(10) DEFAULT NULL,
            `sku` varchar(50) DEFAULT NULL,
            `hsn` varchar(50) DEFAULT NULL,
            `sac` varchar(50) DEFAULT NULL,
            `unit_id` int(10) DEFAULT NULL,
            `alert_qty` int(10) DEFAULT NULL,
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
            `item_group` varchar(50) DEFAULT NULL,
            `parent_id` int(5) DEFAULT NULL,
            `variant_id` int(5) DEFAULT NULL,
            `child_bit` int(1) DEFAULT 0,
            `mrp` double(20,4) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 33 Table: orabooks_db_languages
        $table33 = $wpdb->prefix . 'orabooks_db_languages';
        $sql33 = "CREATE TABLE {$table33} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `language` varchar(50) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 34 Table: orabooks_db_package
        $table34 = $wpdb->prefix . 'orabooks_db_package';
        $sql34 = "CREATE TABLE {$table34} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `package_type` varchar(50) DEFAULT NULL,
            `package_code` varchar(20) DEFAULT NULL,
            `package_name` varchar(50) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `monthly_price` double(20,2) DEFAULT NULL,
            `annual_price` double(20,2) DEFAULT NULL,
            `trial_days` int(10) DEFAULT NULL,
            `max_users` int(10) DEFAULT NULL,
            `max_items` int(10) DEFAULT NULL,
            `max_invoices` int(10) DEFAULT NULL,
            `max_warehouses` int(10) DEFAULT NULL,
            `expire_date` date DEFAULT NULL,
            `system_ip` varchar(50) DEFAULT NULL,
            `system_name` varchar(50) DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(30) DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            `plan_type` varchar(100) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 35 Table: orabooks_db_paymenttypes
        $table35 = $wpdb->prefix . 'orabooks_db_paymenttypes';
        $sql35 = "CREATE TABLE {$table35} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `payment_type` varchar(50) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 36 Table: orabooks_db_paypal
        $table36 = $wpdb->prefix . 'orabooks_db_paypal';
        $sql36 = "CREATE TABLE {$table36} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(10) DEFAULT NULL,
            `sandbox` int(1) DEFAULT NULL,
            `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `updated_at` date DEFAULT NULL,
            `updated_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 37 Table: orabooks_db_paypalpaylog
        $table37 = $wpdb->prefix . 'orabooks_db_paypalpaylog';
        $sql37 = "CREATE TABLE {$table37} (
            `payment_id` int(5) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `product_id` int(11) NOT NULL,
            `txn_id` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `payment_gross` float(10,2) NOT NULL,
            `currency_code` varchar(5) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `payer_email` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `payment_status` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            PRIMARY KEY  (payment_id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 38 Table: orabooks_db_permissions
        $table38 = $wpdb->prefix . 'orabooks_db_permissions';
        $sql38 = "CREATE TABLE {$table38} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `role_id` int(5) DEFAULT NULL,
            `permissions` text DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 39 Table: orabooks_db_purchase
        $table39 = $wpdb->prefix . 'orabooks_db_purchase';
        $sql39 = "CREATE TABLE {$table39} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `warehouse_id` int(5) DEFAULT NULL,
            `count_id` int(10) DEFAULT NULL COMMENT 'Use to create Purchase Code',
            `purchase_code` varchar(50) DEFAULT NULL,
            `reference_no` varchar(50) DEFAULT NULL,
            `purchase_date` date DEFAULT NULL,
            `purchase_status` varchar(50) DEFAULT NULL,
            `supplier_id` int(5) DEFAULT NULL,
            `other_charges_input` double(20,4) DEFAULT NULL,
            `other_charges_tax_id` int(5) DEFAULT NULL,
            `other_charges_amt` double(20,4) DEFAULT NULL,
            `discount_to_all_input` double(20,4) DEFAULT NULL,
            `discount_to_all_type` varchar(50) DEFAULT NULL,
            `tot_discount_to_all_amt` double(20,4) DEFAULT NULL,
            `subtotal` double(20,4) DEFAULT NULL COMMENT 'Purchased qty',
            `round_off` double(20,4) DEFAULT NULL COMMENT 'Pending Qty',
            `grand_total` double(20,4) DEFAULT NULL,
            `purchase_note` text DEFAULT NULL,
            `payment_status` varchar(50) DEFAULT NULL,
            `paid_amount` double(20,4) DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(50) DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `system_ip` varchar(100) DEFAULT NULL,
            `system_name` varchar(100) DEFAULT NULL,
            `company_id` int(5) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            `return_bit` int(1) DEFAULT NULL COMMENT 'Purchase return raised',
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 40 Table: orabooks_db_purchaseitems
        $table40 = $wpdb->prefix . 'orabooks_db_purchaseitems';
        $sql40 = "CREATE TABLE {$table40} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `purchase_id` int(5) DEFAULT NULL,
            `purchase_status` varchar(50) DEFAULT NULL,
            `item_id` int(5) DEFAULT NULL,
            `purchase_qty` double(20,2) DEFAULT NULL,
            `price_per_unit` double(20,4) DEFAULT NULL,
            `tax_type` varchar(50) DEFAULT NULL,
            `tax_id` int(5) DEFAULT NULL,
            `tax_amt` double(20,4) DEFAULT NULL,
            `discount_type` varchar(50) DEFAULT NULL,
            `discount_input` double(20,4) DEFAULT NULL,
            `discount_amt` double(20,4) DEFAULT NULL,
            `unit_total_cost` double(20,4) DEFAULT NULL,
            `total_cost` double(20,4) DEFAULT NULL,
            `profit_margin_per` double(20,4) DEFAULT NULL,
            `unit_sales_price` double(20,4) DEFAULT NULL,
            `status` int(5) DEFAULT NULL,
            `description` text DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 41 Table: orabooks_db_purchaseitemsreturn
        $table41 = $wpdb->prefix . 'orabooks_db_purchaseitemsreturn';
        $sql41 = "CREATE TABLE {$table41} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `purchase_id` int(5) DEFAULT NULL,
            `return_id` int(5) DEFAULT NULL,
            `return_status` varchar(50) DEFAULT NULL,
            `item_id` int(5) DEFAULT NULL,
            `return_qty` double(20,2) DEFAULT NULL,
            `price_per_unit` double(20,4) DEFAULT NULL,
            `tax_id` int(5) DEFAULT NULL,
            `tax_amt` double(20,4) DEFAULT NULL,
            `tax_type` varchar(50) DEFAULT NULL,
            `discount_input` double(20,4) DEFAULT NULL,
            `discount_amt` double(20,4) DEFAULT NULL,
            `discount_type` varchar(50) DEFAULT NULL,
            `unit_total_cost` double(20,4) DEFAULT NULL,
            `total_cost` double(20,4) DEFAULT NULL,
            `profit_margin_per` double(20,4) DEFAULT NULL,
            `unit_sales_price` double(20,4) DEFAULT NULL,
            `status` int(5) DEFAULT NULL,
            `description` text DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 42 Table: orabooks_db_purchasepayments
        $table42 = $wpdb->prefix . 'orabooks_db_purchasepayments';
        $sql42 = "CREATE TABLE {$table42} (
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
        ) ENGINE=InnoDB {$charset_collate};";

        // 43 Table: orabooks_db_purchasepaymentsreturn
        $table43 = $wpdb->prefix . 'orabooks_db_purchasepaymentsreturn';
        $sql43 = "CREATE TABLE {$table43} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `count_id` int(5) DEFAULT NULL,
            `payment_code` varchar(50) DEFAULT NULL,
            `store_id` int(11) DEFAULT NULL,
            `purchase_id` int(11) DEFAULT NULL,
            `return_id` int(5) DEFAULT NULL,
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
        ) ENGINE=InnoDB {$charset_collate};";

        // 44 Table: orabooks_db_purchasereturn
        $table44 = $wpdb->prefix . 'orabooks_db_purchasereturn';
        $sql44 = "CREATE TABLE {$table44} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `count_id` int(10) DEFAULT NULL COMMENT 'Use to create Purchase Return Code',
            `warehouse_id` int(5) DEFAULT NULL,
            `purchase_id` int(11) DEFAULT NULL,
            `return_code` varchar(50) DEFAULT NULL,
            `reference_no` varchar(50) DEFAULT NULL,
            `return_date` date DEFAULT NULL,
            `return_status` varchar(50) DEFAULT NULL,
            `supplier_id` int(5) DEFAULT NULL,
            `other_charges_input` double(20,4) DEFAULT NULL,
            `other_charges_tax_id` int(5) DEFAULT NULL,
            `other_charges_amt` double(20,4) DEFAULT NULL,
            `discount_to_all_input` double(20,4) DEFAULT NULL,
            `discount_to_all_type` varchar(50) DEFAULT NULL,
            `tot_discount_to_all_amt` double(20,4) DEFAULT NULL,
            `subtotal` double(20,4) DEFAULT NULL COMMENT 'Purchased qty',
            `round_off` double(20,4) DEFAULT NULL COMMENT 'Pending Qty',
            `grand_total` double(20,4) DEFAULT NULL,
            `return_note` text DEFAULT NULL,
            `payment_status` varchar(50) DEFAULT NULL,
            `paid_amount` double(20,4) DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(50) DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `system_ip` varchar(100) DEFAULT NULL,
            `system_name` varchar(100) DEFAULT NULL,
            `company_id` int(5) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
            ) ENGINE=InnoDB {$charset_collate};";

        // 45 Table: orabooks_db_quotation
        $table45 = $wpdb->prefix . 'orabooks_db_quotation';
        $sql45 = "CREATE TABLE {$table45} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `warehouse_id` int(5) DEFAULT NULL,
            `count_id` int(10) DEFAULT NULL COMMENT 'Use to create quotation Code',
            `quotation_code` varchar(50) DEFAULT NULL,
            `reference_no` varchar(50) DEFAULT NULL,
            `quotation_date` date DEFAULT NULL,
            `expire_date` date DEFAULT NULL,
            `quotation_status` varchar(50) DEFAULT NULL,
            `customer_id` int(5) DEFAULT NULL,
            `other_charges_input` double(20,4) DEFAULT NULL,
            `other_charges_tax_id` int(5) DEFAULT NULL,
            `other_charges_amt` double(20,4) DEFAULT NULL,
            `discount_to_all_input` double(20,4) DEFAULT NULL,
            `discount_to_all_type` varchar(50) DEFAULT NULL,
            `tot_discount_to_all_amt` double(20,4) DEFAULT NULL,
            `subtotal` double(20,4) DEFAULT NULL,
            `round_off` double(20,4) DEFAULT NULL,
            `grand_total` double(20,4) DEFAULT NULL,
            `quotation_note` text DEFAULT NULL,
            `payment_status` varchar(50) DEFAULT NULL,
            `paid_amount` double(20,4) DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(50) DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `system_ip` varchar(100) DEFAULT NULL,
            `system_name` varchar(100) DEFAULT NULL,
            `company_id` int(5) DEFAULT NULL,
            `pos` int(1) DEFAULT NULL COMMENT '1=yes 0=no',
            `status` int(1) DEFAULT NULL,
            `return_bit` int(1) DEFAULT NULL COMMENT 'quotation return raised',
            `customer_previous_due` double(20,4) DEFAULT NULL,
            `customer_total_due` double(20,4) DEFAULT NULL,
            `sales_status` varchar(50) DEFAULT NULL,
            PRIMARY KEY  (id)
            ) ENGINE=InnoDB {$charset_collate};";

        // 46 Table: orabooks_db_quotationitems
        $table46 = $wpdb->prefix . 'orabooks_db_quotationitems';
        $sql46 = "CREATE TABLE {$table46} (
              `id` int(5) NOT NULL AUTO_INCREMENT,
              `store_id` int(11) DEFAULT NULL,
              `quotation_id` int(5) DEFAULT NULL,
              `quotation_status` varchar(50) DEFAULT NULL,
              `item_id` int(5) DEFAULT NULL,
              `description` text DEFAULT NULL,
              `quotation_qty` double(20,2) DEFAULT NULL,
              `price_per_unit` double(20,4) DEFAULT NULL,
              `tax_type` varchar(50) DEFAULT NULL,
              `tax_id` int(5) DEFAULT NULL,
              `tax_amt` double(20,4) DEFAULT NULL,
              `discount_type` varchar(50) DEFAULT NULL,
              `discount_input` double(20,4) DEFAULT NULL,
              `discount_amt` double(20,4) DEFAULT NULL,
              `unit_total_cost` double(20,4) DEFAULT NULL,
              `total_cost` double(20,4) DEFAULT NULL,
              `status` int(5) DEFAULT NULL,
              `seller_points` double(20,4) DEFAULT 0.0000,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 47 Table: orabooks_db_roles
        $table47 = $wpdb->prefix . 'orabooks_db_roles';
        $sql47 = "CREATE TABLE {$table47} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `role_name` varchar(50) DEFAULT NULL,
            `description` mediumtext DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 48 Table: orabooks_db_sales
        $table48 = $wpdb->prefix . 'orabooks_db_sales';
        $sql48 = "CREATE TABLE {$table48} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `warehouse_id` int(5) DEFAULT NULL,
            `init_code` varchar(100) DEFAULT NULL,
            `count_id` decimal(20,0) DEFAULT NULL COMMENT 'Use to create Sales Code',
            `sales_code` varchar(50) DEFAULT NULL,
            `reference_no` varchar(50) DEFAULT NULL,
            `sales_date` date DEFAULT NULL,
            `due_date` date DEFAULT NULL,
            `sales_status` varchar(50) DEFAULT NULL,
            `customer_id` int(5) DEFAULT NULL,
            `other_charges_input` double(20,2) DEFAULT NULL,
            `other_charges_tax_id` int(5) DEFAULT NULL,
            `other_charges_amt` double(20,2) DEFAULT NULL,
            `discount_to_all_input` double(20,2) DEFAULT NULL,
            `discount_to_all_type` varchar(50) DEFAULT NULL,
            `tot_discount_to_all_amt` double(20,2) DEFAULT NULL,
            `subtotal` double(20,2) DEFAULT NULL,
            `round_off` double(20,2) DEFAULT NULL,
            `grand_total` double(20,4) DEFAULT NULL,
            `sales_note` text DEFAULT NULL,
            `payment_status` varchar(50) DEFAULT NULL,
            `paid_amount` double(20,4) DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(50) DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `system_ip` varchar(100) DEFAULT NULL,
            `system_name` varchar(100) DEFAULT NULL,
            `company_id` int(5) DEFAULT NULL,
            `pos` int(1) DEFAULT NULL COMMENT '1=yes 0=no',
            `status` int(1) DEFAULT NULL,
            `return_bit` int(1) DEFAULT NULL COMMENT 'sales return raised',
            `customer_previous_due` double(20,2) DEFAULT NULL,
            `customer_total_due` double(20,2) DEFAULT NULL,
            `quotation_id` int(5) DEFAULT NULL,
            `coupon_id` int(10) DEFAULT NULL,
            `coupon_amt` double(20,2) DEFAULT 0.00,
            `invoice_terms` text DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 49 Table: orabooks_db_salesitems
        $table49 = $wpdb->prefix . 'orabooks_db_salesitems';
        $sql49 = "CREATE TABLE {$table49} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `sales_id` int(5) DEFAULT NULL,
            `sales_status` varchar(50) DEFAULT NULL,
            `item_id` int(5) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `sales_qty` double(20,2) DEFAULT NULL,
            `price_per_unit` double(20,4) DEFAULT NULL,
            `tax_type` varchar(50) DEFAULT NULL,
            `tax_id` int(5) DEFAULT NULL,
            `tax_amt` double(20,4) DEFAULT NULL,
            `discount_type` varchar(50) DEFAULT NULL,
            `discount_input` double(20,4) DEFAULT NULL,
            `discount_amt` double(20,4) DEFAULT NULL,
            `unit_total_cost` double(20,4) DEFAULT NULL,
            `total_cost` double(20,4) DEFAULT NULL,
            `status` int(5) DEFAULT NULL,
            `seller_points` double(20,2) DEFAULT 0.00,
            `purchase_price` double(20,3) DEFAULT 0.000,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 50 Table: orabooks_db_salesitemsreturn
        $table50 = $wpdb->prefix . 'orabooks_db_salesitemsreturn';
        $sql50 = "CREATE TABLE {$table50} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `sales_id` int(5) DEFAULT NULL,
            `return_id` int(5) DEFAULT NULL,
            `return_status` varchar(50) DEFAULT NULL,
            `item_id` int(5) DEFAULT NULL,
            `return_qty` double(20,2) DEFAULT NULL,
            `price_per_unit` double(20,4) DEFAULT NULL,
            `tax_type` varchar(50) DEFAULT NULL,
            `tax_id` int(5) DEFAULT NULL,
            `tax_amt` double(20,4) DEFAULT NULL,
            `discount_input` double(20,4) DEFAULT NULL,
            `discount_amt` double(20,4) DEFAULT NULL,
            `discount_type` varchar(50) DEFAULT NULL,
            `unit_total_cost` double(20,4) DEFAULT NULL,
            `total_cost` double(20,4) DEFAULT NULL,
            `status` int(5) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `purchase_price` double(20,3) DEFAULT 0.000,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 51 Table: orabooks_db_salespayments
        $table51 = $wpdb->prefix . 'orabooks_db_salespayments';
        $sql51 = "CREATE TABLE {$table51} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `count_id` int(5) DEFAULT NULL,
            `payment_code` varchar(50) DEFAULT NULL,
            `store_id` int(11) DEFAULT NULL,
            `sales_id` int(5) DEFAULT NULL,
            `payment_date` date DEFAULT NULL,
            `payment_type` varchar(50) DEFAULT NULL,
            `payment` double(20,4) DEFAULT NULL,
            `payment_note` text DEFAULT NULL,
            `change_return` double(20,4) DEFAULT NULL COMMENT 'Refunding the greater amount',
            `system_ip` varchar(50) DEFAULT NULL,
            `system_name` varchar(50) DEFAULT NULL,
            `created_time` varchar(50) DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            `account_id` int(5) DEFAULT NULL,
            `customer_id` int(5) DEFAULT NULL,
            `short_code` varchar(50) DEFAULT NULL,
            `advance_adjusted` double(20,4) DEFAULT NULL,
            `cheque_number` varchar(100) DEFAULT NULL,
            `cheque_period` int(10) DEFAULT NULL,
            `cheque_status` varchar(100) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 52 Table: orabooks_db_salespaymentsreturn
        $table52 = $wpdb->prefix . 'orabooks_db_salespaymentsreturn';
        $sql52 = "CREATE TABLE {$table52} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `count_id` int(5) DEFAULT NULL,
            `payment_code` varchar(50) DEFAULT NULL,
            `store_id` int(11) DEFAULT NULL,
            `sales_id` int(5) DEFAULT NULL,
            `return_id` int(5) DEFAULT NULL,
            `payment_date` date DEFAULT NULL,
            `payment_type` varchar(50) DEFAULT NULL,
            `payment` double(20,4) DEFAULT NULL,
            `payment_note` text DEFAULT NULL,
            `change_return` double(20,4) DEFAULT NULL COMMENT 'Refunding the greater amount',
            `system_ip` varchar(50) DEFAULT NULL,
            `system_name` varchar(50) DEFAULT NULL,
            `created_time` time DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            `account_id` int(5) DEFAULT NULL,
            `customer_id` int(5) DEFAULT NULL,
            `short_code` varchar(50) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 53 Table: orabooks_db_salesreturn
        $table53 = $wpdb->prefix . 'orabooks_db_salesreturn';
        $sql53 = "CREATE TABLE {$table53} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `count_id` int(10) DEFAULT NULL COMMENT 'Use to create Sales Return Code',
            `sales_id` int(5) DEFAULT NULL,
            `warehouse_id` int(5) DEFAULT NULL,
            `return_code` varchar(50) DEFAULT NULL,
            `reference_no` varchar(50) DEFAULT NULL,
            `return_date` date DEFAULT NULL,
            `return_status` varchar(50) DEFAULT NULL,
            `customer_id` int(5) DEFAULT NULL,
            `other_charges_input` double(20,4) DEFAULT NULL,
            `other_charges_tax_id` int(5) DEFAULT NULL,
            `other_charges_amt` double(20,4) DEFAULT NULL,
            `discount_to_all_input` double(20,4) DEFAULT NULL,
            `discount_to_all_type` varchar(50) DEFAULT NULL,
            `tot_discount_to_all_amt` double(20,4) DEFAULT NULL,
            `subtotal` double(20,4) DEFAULT NULL,
            `round_off` double(20,4) DEFAULT NULL,
            `grand_total` double(20,4) DEFAULT NULL,
            `return_note` text DEFAULT NULL,
            `payment_status` varchar(50) DEFAULT NULL,
            `paid_amount` double(20,4) DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(50) DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `system_ip` varchar(100) DEFAULT NULL,
            `system_name` varchar(100) DEFAULT NULL,
            `company_id` int(5) DEFAULT NULL,
            `pos` int(1) DEFAULT NULL COMMENT '1=yes 0=no',
            `status` int(1) DEFAULT NULL,
            `return_bit` int(1) DEFAULT NULL COMMENT 'Return raised or not 1 or null',
            `coupon_id` int(11) DEFAULT NULL,
            `coupon_amt` double(20,2) DEFAULT 0.00,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 54 Table: orabooks_db_shippingaddress
        $table54 = $wpdb->prefix . 'orabooks_db_shippingaddress';
        $sql54 = "CREATE TABLE {$table54} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `count_id` int(10) DEFAULT NULL COMMENT 'Use to create Sales Return Code',
            `sales_id` int(5) DEFAULT NULL,
            `warehouse_id` int(5) DEFAULT NULL,
            `return_code` varchar(50) DEFAULT NULL,
            `reference_no` varchar(50) DEFAULT NULL,
            `return_date` date DEFAULT NULL,
            `return_status` varchar(50) DEFAULT NULL,
            `customer_id` int(5) DEFAULT NULL,
            `other_charges_input` double(20,4) DEFAULT NULL,
            `other_charges_tax_id` int(5) DEFAULT NULL,
            `other_charges_amt` double(20,4) DEFAULT NULL,
            `discount_to_all_input` double(20,4) DEFAULT NULL,
            `discount_to_all_type` varchar(50) DEFAULT NULL,
            `tot_discount_to_all_amt` double(20,4) DEFAULT NULL,
            `subtotal` double(20,4) DEFAULT NULL,
            `round_off` double(20,4) DEFAULT NULL,
            `grand_total` double(20,4) DEFAULT NULL,
            `return_note` text DEFAULT NULL,
            `payment_status` varchar(50) DEFAULT NULL,
            `paid_amount` double(20,4) DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(50) DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `system_ip` varchar(100) DEFAULT NULL,
            `system_name` varchar(100) DEFAULT NULL,
            `company_id` int(5) DEFAULT NULL,
            `pos` int(1) DEFAULT NULL COMMENT '1=yes 0=no',
            `status` int(1) DEFAULT NULL,
            `return_bit` int(1) DEFAULT NULL COMMENT 'Return raised or not 1 or null',
            `coupon_id` int(11) DEFAULT NULL,
            `coupon_amt` double(20,2) DEFAULT 0.00,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 55 Table: orabooks_db_sitesettings
        $table55 = $wpdb->prefix . 'orabooks_db_sitesettings';
        $sql55 = "CREATE TABLE {$table55} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `version` varchar(10) DEFAULT NULL,
            `site_name` varchar(100) DEFAULT NULL,
            `logo` mediumtext DEFAULT NULL COMMENT 'path',
            `machine_id` text DEFAULT NULL,
            `domain` text DEFAULT NULL,
            `unique_code` text DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 56 Table: orabooks_db_smsapi
        $table56 = $wpdb->prefix . 'orabooks_db_smsapi';
        $sql56 = "CREATE TABLE {$table56} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `info` varchar(150) DEFAULT NULL,
            `api_key` varchar(600) DEFAULT NULL,
            `key_value` varchar(600) DEFAULT NULL,
            `delete_bit` int(5) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 57 Table: orabooks_db_smstemplates
        $table57 = $wpdb->prefix . 'orabooks_db_smstemplates';
        $sql57 = "CREATE TABLE {$table57} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `template_name` varchar(100) DEFAULT NULL,
            `content` text DEFAULT NULL,
            `variables` text DEFAULT NULL,
            `status` int(5) DEFAULT NULL,
            `undelete_bit` int(5) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 58 Table: orabooks_db_sobpayments
        $table58 = $wpdb->prefix . 'orabooks_db_sobpayments';
        $sql58 = "CREATE TABLE {$table58} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `supplier_id` int(5) DEFAULT NULL,
            `payment_date` date DEFAULT NULL,
            `payment_type` varchar(50) DEFAULT NULL,
            `payment` double(10,2) DEFAULT NULL,
            `payment_note` mediumtext DEFAULT NULL,
            `system_ip` varchar(50) DEFAULT NULL,
            `system_name` varchar(50) DEFAULT NULL,
            `created_time` time DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 59 Table: orabooks_db_states
        $table59 = $wpdb->prefix . 'orabooks_db_states';
        $sql59 = "CREATE TABLE {$table59} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `state_code` varchar(10) DEFAULT NULL,
            `state` varchar(4050) DEFAULT NULL,
            `country_code` varchar(15) DEFAULT NULL,
            `country_id` int(5) DEFAULT NULL,
            `country` varchar(15) DEFAULT NULL,
            `added_on` date DEFAULT NULL,
            `company_id` int(5) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 60 Table: orabooks_db_stockadjustment
        $table60 = $wpdb->prefix . 'orabooks_db_stockadjustment';
        $sql60 = "CREATE TABLE {$table60} (
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
        ) ENGINE=InnoDB {$charset_collate};";

        // 61 Table: orabooks_db_stockadjustmentitems
        $table61 = $wpdb->prefix . 'orabooks_db_stockadjustmentitems';
        $sql61 = "CREATE TABLE {$table61} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `warehouse_id` int(5) DEFAULT NULL,
            `adjustment_id` int(5) DEFAULT NULL,
            `item_id` int(5) DEFAULT NULL,
            `adjustment_qty` double(20,2) DEFAULT NULL,
            `status` int(5) DEFAULT NULL,
            `description` text DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 62 Table: orabooks_db_stockentry
        $table62 = $wpdb->prefix . 'orabooks_db_stockentry';
        $sql62 = "CREATE TABLE {$table62} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `entry_date` date DEFAULT NULL,
            `item_id` int(11) DEFAULT NULL,
            `qty` int(5) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 63 Table: orabooks_db_stocktransfer
        $table63 = $wpdb->prefix . 'orabooks_db_stocktransfer';
        $sql63 = "CREATE TABLE {$table63} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL COMMENT 'from store',
            `to_store_id` int(11) DEFAULT NULL COMMENT 'to store transfer',
            `warehouse_from` int(5) DEFAULT NULL,
            `warehouse_to` int(5) DEFAULT NULL,
            `transfer_date` date DEFAULT NULL,
            `note` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
            `created_by` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
            `system_ip` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
            `system_name` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 64 Table: orabooks_db_stocktransferitems
        $table64 = $wpdb->prefix . 'orabooks_db_stocktransferitems';
        $sql64 = "CREATE TABLE {$table64} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `stocktransfer_id` int(5) DEFAULT NULL,
            `store_id` int(5) DEFAULT NULL COMMENT 'from store',
            `to_store_id` int(5) DEFAULT NULL COMMENT 'to store',
            `warehouse_from` int(5) DEFAULT NULL COMMENT 'warehouse ids',
            `warehouse_to` int(11) DEFAULT NULL COMMENT 'warehouse ids',
            `item_id` int(11) DEFAULT NULL,
            `transfer_qty` double(20,2) DEFAULT NULL,
            `status` int(5) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 65 Table: orabooks_db_store
        $table65 = $wpdb->prefix . 'orabooks_db_store';
        $sql65 = "CREATE TABLE {$table65} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_code` varchar(150) DEFAULT NULL,
            `store_name` varchar(150) DEFAULT NULL,
            `store_website` varchar(150) DEFAULT NULL,
            `mobile` varchar(150) DEFAULT NULL,
            `phone` varchar(150) DEFAULT NULL,
            `email` varchar(100) DEFAULT NULL,
            `website` varchar(250) DEFAULT NULL,
            `store_logo` text DEFAULT NULL,
            `logo` mediumtext DEFAULT NULL,
            `upi_id` varchar(50) DEFAULT NULL,
            `upi_code` text DEFAULT NULL,
            `country` varchar(150) DEFAULT NULL,
            `state` varchar(150) DEFAULT NULL,
            `city` varchar(100) DEFAULT NULL,
            `address` varchar(300) DEFAULT NULL,
            `postcode` varchar(50) DEFAULT NULL,
            `gst_no` varchar(50) DEFAULT NULL,
            `vat_no` varchar(50) DEFAULT NULL,
            `pan_no` varchar(50) DEFAULT NULL,
            `bank_details` mediumtext DEFAULT NULL,
            `cid` int(50) DEFAULT NULL,
            `category_init` varchar(50) DEFAULT NULL,
            `item_init` varchar(50) DEFAULT NULL COMMENT 'INITAL CODE',
            `supplier_init` varchar(50) DEFAULT NULL COMMENT 'INITAL CODE',
            `purchase_init` varchar(50) DEFAULT NULL COMMENT 'INITAL CODE',
            `purchase_return_init` varchar(50) DEFAULT NULL,
            `customer_init` varchar(50) DEFAULT NULL COMMENT 'INITAL CODE',
            `sales_init` varchar(50) DEFAULT NULL COMMENT 'INITAL CODE',
            `sales_return_init` varchar(50) DEFAULT NULL,
            `expense_init` varchar(50) DEFAULT NULL,
            `accounts_init` varchar(50) DEFAULT NULL,
            `journal_init` varchar(50) DEFAULT NULL,
            `cust_advance_init` varchar(50) DEFAULT NULL,
            `invoice_view` int(5) DEFAULT NULL COMMENT '1=Standard,2=Indian GST',
            `sms_status` int(1) DEFAULT NULL COMMENT '1=Enable 0=Disable',
            `status` int(1) DEFAULT NULL,
            `language_id` int(5) DEFAULT NULL,
            `currency_id` int(5) DEFAULT NULL,
            `currency_placement` varchar(50) DEFAULT NULL,
            `timezone` varchar(50) DEFAULT NULL,
            `date_format` varchar(50) DEFAULT NULL,
            `time_format` int(5) DEFAULT NULL,
            `sales_discount` double(20,4) DEFAULT NULL,
            `currencysymbol_id` int(5) DEFAULT NULL,
            `regno_key` varchar(50) DEFAULT NULL,
            `fav_icon` text DEFAULT NULL,
            `purchase_code` text DEFAULT NULL,
            `change_return` int(2) DEFAULT NULL,
            `sales_invoice_format_id` int(5) DEFAULT NULL,
            `pos_invoice_format_id` int(5) DEFAULT NULL,
            `sales_invoice_footer_text` text DEFAULT NULL,
            `round_off` int(1) DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(50) DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `system_ip` varchar(50) DEFAULT NULL,
            `system_name` varchar(50) DEFAULT NULL,
            `quotation_init` varchar(50) DEFAULT NULL,
            `decimals` int(1) DEFAULT 2,
            `money_transfer_init` varchar(50) DEFAULT NULL,
            `sales_payment_init` varchar(50) DEFAULT NULL,
            `sales_return_payment_init` varchar(50) DEFAULT NULL,
            `purchase_payment_init` varchar(50) DEFAULT NULL,
            `purchase_return_payment_init` varchar(50) DEFAULT NULL,
            `expense_payment_init` varchar(50) DEFAULT NULL,
            `current_subscriptionlist_id` int(10) DEFAULT 0,
            `smtp_host` varchar(250) DEFAULT NULL,
            `smtp_port` varchar(250) DEFAULT NULL,
            `smtp_user` varchar(250) DEFAULT NULL,
            `smtp_pass` varchar(250) DEFAULT NULL,
            `smtp_status` int(1) DEFAULT 0,
            `sms_url` text DEFAULT NULL,
            `user_id` int(5) NOT NULL,
            `mrp_column` int(1) DEFAULT 0,
            `invoice_terms` text DEFAULT NULL,
            `previous_balance_bit` int(1) DEFAULT 1 COMMENT '1=Show, 0=Hide - Shows on sales invoice',
            `qty_decimals` int(5) DEFAULT 2,
            `signature` text DEFAULT NULL,
            `show_signature` int(1) DEFAULT 0,
            `t_and_c_status` int(1) DEFAULT 1 COMMENT '1=Show, 0=Hide - Shows on sales invoice',
            `t_and_c_status_pos` int(1) DEFAULT 1,
            `number_to_words` varchar(250) DEFAULT 'Default',
            `default_account_id` int(10) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 66 Table: orabooks_db_stripe
        $table66 = $wpdb->prefix . 'orabooks_db_stripe';
        $sql66 = "CREATE TABLE {$table66} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `sandbox` int(1) DEFAULT NULL,
            `publishable_key` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `api_secret` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `updated_at` date DEFAULT NULL,
            `updated_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 67 Table: orabooks_db_stripepayments
        $table67 = $wpdb->prefix . 'orabooks_db_stripepayments';
        $sql67 = "CREATE TABLE {$table67} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `product_id` int(11) NOT NULL,
            `buyer_name` varchar(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `buyer_email` varchar(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `paid_amount` varchar(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `paid_amount_currency` varchar(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `txn_id` varchar(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `payment_status` varchar(25) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `created` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 68 Table: orabooks_db_subscription
        $table68 = $wpdb->prefix . 'orabooks_db_subscription';
        $sql68 = "CREATE TABLE {$table68} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `payment_id` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `package_id` int(5) DEFAULT NULL,
            `package_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `package_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `subscription_date` date DEFAULT NULL,
            `expire_date` date DEFAULT NULL,
            `trial_days` int(10) DEFAULT NULL,
            `max_users` int(10) DEFAULT NULL,
            `max_warehouses` int(10) DEFAULT NULL,
            `max_items` int(10) DEFAULT NULL,
            `max_invoices` int(10) DEFAULT NULL,
            `payment_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `txn_id` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `payment_gross` double(10,2) DEFAULT NULL,
            `currency_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `payer_email` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `payment_status` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `created_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `system_ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `system_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `package_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `payment_type` varchar(250) DEFAULT NULL COMMENT 'manual subscription only',
            `package_count` int(10) DEFAULT NULL COMMENT 'manual subscription only',
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 69 Table: orabooks_db_suppliers
        $table69 = $wpdb->prefix . 'orabooks_db_suppliers';
        $sql69 = "CREATE TABLE {$table69} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `count_id` int(10) DEFAULT NULL COMMENT 'Use to create supplier Code',
            `supplier_code` varchar(20) DEFAULT NULL,
            `supplier_name` varchar(50) DEFAULT NULL,
            `mobile` varchar(15) DEFAULT NULL,
            `phone` varchar(15) DEFAULT NULL,
            `email` varchar(50) DEFAULT NULL,
            `gstin` varchar(100) DEFAULT NULL,
            `tax_number` varchar(50) DEFAULT NULL,
            `vatin` varchar(100) DEFAULT NULL,
            `opening_balance` double(20,4) DEFAULT NULL,
            `purchase_due` double(20,4) DEFAULT NULL,
            `purchase_return_due` double(20,4) DEFAULT NULL,
            `country_id` varchar(50) DEFAULT NULL,
            `state_id` varchar(50) DEFAULT NULL,
            `city` varchar(50) DEFAULT NULL,
            `postcode` varchar(10) DEFAULT NULL,
            `address` varchar(250) DEFAULT NULL,
            `system_ip` varchar(50) DEFAULT NULL,
            `system_name` varchar(50) DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(30) DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `company_id` int(5) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 70 Table: orabooks_db_supplier_payments
        $table70 = $wpdb->prefix . 'orabooks_db_supplier_payments';
        $sql70 = "CREATE TABLE {$table70} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `purchasepayment_id` int(5) DEFAULT NULL,
            `supplier_id` int(5) DEFAULT NULL,
            `payment_date` date DEFAULT NULL,
            `payment_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `payment` double(10,2) DEFAULT NULL,
            `payment_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `system_ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `system_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `created_time` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `created_date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `created_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 71 Table: orabooks_db_tax
        $table71 = $wpdb->prefix . 'orabooks_db_tax';
        $sql71 = "CREATE TABLE {$table71} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `tax_name` varchar(50) DEFAULT NULL,
            `tax` double(20,4) DEFAULT NULL,
            `group_bit` int(1) DEFAULT NULL COMMENT '1=Yes, 0=No',
            `subtax_ids` varchar(10) DEFAULT NULL COMMENT 'Tax groups IDs',
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 72 Table: orabooks_db_timezone
        $table72 = $wpdb->prefix . 'orabooks_db_timezone';
        $sql72 = "CREATE TABLE {$table72} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `timezone` varchar(100) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 73 Table: orabooks_db_twilio
        $table73 = $wpdb->prefix . 'orabooks_db_twilio';
        $sql73 = "CREATE TABLE {$table73} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(5) DEFAULT NULL,
            `account_sid` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `auth_token` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `twilio_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            `status` int(1) DEFAULT 0,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 74 Table: orabooks_db_units
        $table74 = $wpdb->prefix . 'orabooks_db_units';
        $sql74 = "CREATE TABLE {$table74} (
            `id` int(5) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `unit_name` varchar(50) DEFAULT NULL,
            `description` mediumtext DEFAULT NULL,
            `company_id` int(5) DEFAULT NULL,
            `status` int(1) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 75 Table: orabooks_ac_coa_types
        $table75 = $wpdb->prefix . 'orabooks_ac_coa_types';
        $sql75 = "CREATE TABLE {$table75} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `coa_type` varchar(100) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `status` int(1) DEFAULT 1,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 76 Table: orabooks_ac_coa_list
        $table76 = $wpdb->prefix . 'orabooks_ac_coa_list';
        $sql76 = "CREATE TABLE {$table76} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `coa_type_id` int(11) DEFAULT NULL,
            `account_code` varchar(10) DEFAULT NULL,
            `account_name` varchar(150) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `tax_id` int(11) DEFAULT NULL,
            `status` int(1) DEFAULT 1,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY  (account_code)
        ) ENGINE=InnoDB {$charset_collate};";

        // 77 Table: orabooks_ac_journal_entry
        $table77 = $wpdb->prefix . 'orabooks_ac_journal_entry';
        $sql77 = "CREATE TABLE {$table77} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `organization_id` int(11) DEFAULT NULL,
            `entry_date` date DEFAULT NULL,
            `posting_date` date DEFAULT NULL,
            `document_date` date DEFAULT NULL,
            `entry_number` varchar(50) DEFAULT NULL,
            `source_type` varchar(50) DEFAULT NULL,
            `source_id` int(11) DEFAULT NULL,
            `reference_no` varchar(100) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `total_debit` double(20,4) DEFAULT 0.0000,
            `total_credit` double(20,4) DEFAULT 0.0000,
            `status` varchar(20) DEFAULT 'Posted',
            `reversal_of` int(11) DEFAULT NULL,
            `reversed_by` varchar(50) DEFAULT NULL,
            `currency` varchar(10) DEFAULT NULL,
            `base_currency` varchar(10) DEFAULT NULL,
            `locked` int(1) DEFAULT 0,
            `created_by` varchar(50) DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `posted_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 78 Table: orabooks_ac_journal_line
        $table78 = $wpdb->prefix . 'orabooks_ac_journal_line';
        $sql78 = "CREATE TABLE {$table78} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `journal_entry_id` int(11) NOT NULL,
            `account_id` int(11) NOT NULL,
            `contact_id` int(11) DEFAULT NULL,
            `debit` double(20,4) DEFAULT 0.0000,
            `credit` double(20,4) DEFAULT 0.0000,
            `debit_amt` double(20,4) DEFAULT 0.0000,
            `credit_amt` double(20,4) DEFAULT 0.0000,
            `description` text DEFAULT NULL,
            `currency` varchar(10) DEFAULT NULL,
            `exchange_rate` double(20,4) DEFAULT 1.0000,
            `amount_base` double(20,4) DEFAULT 0.0000,
            `line_number` int(11) DEFAULT NULL,
            `status` int(1) DEFAULT 1,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 79 Table: orabooks_db_sidebar
        $table79 = $wpdb->prefix . 'orabooks_db_sidebar';
        $sql79 = "CREATE TABLE {$table79} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `module` varchar(50) DEFAULT NULL,
            `parent` int(11) DEFAULT 0,
            `menu_title` varchar(100) DEFAULT NULL,
            `menu_slug` varchar(100) DEFAULT NULL,
            `icon` varchar(100) DEFAULT NULL,
            `sort_order` int(5) DEFAULT 0,
            `status` int(1) DEFAULT 1,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `created_by` int(11) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 80 Table: orabooks_ac_opening_balances
        $table80 = $wpdb->prefix . 'orabooks_ac_opening_balances';
        $sql80 = "CREATE TABLE {$table80} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `account_id` int(11) DEFAULT NULL,
            `account_type` varchar(50) DEFAULT NULL,
            `party_id` int(11) DEFAULT NULL,
            `party_type` varchar(50) DEFAULT NULL,
            `debit` double(20,4) DEFAULT 0.0000,
            `credit` double(20,4) DEFAULT 0.0000,
            `entry_date` date DEFAULT NULL,
            `description` text DEFAULT NULL,
            `status` varchar(20) DEFAULT 'Draft',
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 81 Table: orabooks_ac_inventory_opening
        $table81 = $wpdb->prefix . 'orabooks_ac_inventory_opening';
        $sql81 = "CREATE TABLE {$table81} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `item_id` int(11) DEFAULT NULL,
            `quantity` double(20,4) DEFAULT 0.0000,
            `unit_cost` double(20,4) DEFAULT 0.0000,
            `total_cost` double(20,4) DEFAULT 0.0000,
            `entry_date` date DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 82 Table: orabooks_ac_assets
        $table82 = $wpdb->prefix . 'orabooks_ac_assets';
        $sql82 = "CREATE TABLE {$table82} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `name` varchar(150) NOT NULL,
            `category` varchar(100) DEFAULT NULL,
            `purchase_date` date NOT NULL,
            `cost` double(20,4) NOT NULL DEFAULT 0.0000,
            `salvage_value` double(20,4) DEFAULT 0.0000,
            `useful_life_years` int(11) NOT NULL,
            `depreciation_method` varchar(50) DEFAULT 'straight_line',
            `asset_account_id` int(11) DEFAULT NULL,
            `depr_expense_account_id` int(11) DEFAULT NULL,
            `accum_depr_account_id` int(11) DEFAULT NULL,
            `payment_type_id` int(11) DEFAULT NULL,
            `bank_account_id` int(11) DEFAULT NULL,
            `journal_entry_id` int(11) DEFAULT NULL,
            `status` varchar(20) DEFAULT 'Active',
            `created_by` varchar(50) DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 83 Table: orabooks_ac_depreciation_records
        $table83 = $wpdb->prefix . 'orabooks_ac_depreciation_records';
        $sql83 = "CREATE TABLE {$table83} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `asset_id` int(11) NOT NULL,
            `period_date` date NOT NULL,
            `depreciation_amount` double(20,4) NOT NULL,
            `accumulated_amount` double(20,4) NOT NULL,
            `journal_entry_id` int(11) DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 84 Table: orabooks_ac_asset_disposals
        $table84 = $wpdb->prefix . 'orabooks_ac_asset_disposals';
        $sql84 = "CREATE TABLE {$table84} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `asset_id` int(11) NOT NULL,
            `disposal_date` date NOT NULL,
            `sale_price` double(20,4) DEFAULT 0.0000,
            `gain_loss` double(20,4) DEFAULT 0.0000,
            `journal_entry_id` int(11) DEFAULT NULL,
            `note` text DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 85 Table: orabooks_ac_depreciation_methods
        $table85 = $wpdb->prefix . 'orabooks_ac_depreciation_methods';
        $sql85 = "CREATE TABLE {$table85} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `slug` varchar(100) NOT NULL,
            `description` text,
            `status` int(11) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 86 Table: orabooks_user_permissions
        $table86 = $wpdb->prefix . 'orabooks_user_permissions';
        $sql86 = "CREATE TABLE {$table86} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) NOT NULL,
            `sidebar_ids` text DEFAULT NULL,
            `status` int(1) DEFAULT 1,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 87 Table: orabooks_ac_asset_category
        $table87 = $wpdb->prefix . 'orabooks_ac_asset_category';
        $sql87 = "CREATE TABLE {$table87} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `category_code` varchar(50) DEFAULT NULL,
            `category_name` varchar(150) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `depreciation_method` varchar(50) DEFAULT 'straight_line',
            `default_useful_life_years` int(3) DEFAULT NULL,
            `created_by` varchar(50) DEFAULT NULL,
            `created_date` date DEFAULT NULL,
            `created_time` varchar(50) DEFAULT NULL,
            `system_ip` varchar(50) DEFAULT NULL,
            `system_name` varchar(100) DEFAULT NULL,
            `status` int(1) DEFAULT 1,
            PRIMARY KEY  (id),
            UNIQUE KEY category_code (category_code)
        ) ENGINE=InnoDB {$charset_collate};";

        // 88 Table: orabooks_ac_fiscal_years
        $table88 = $wpdb->prefix . 'orabooks_ac_fiscal_years';
        $sql88 = "CREATE TABLE {$table88} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `fiscal_year_name` varchar(100) NOT NULL,
            `start_date` date NOT NULL,
            `end_date` date NOT NULL,
            `description` text,
            `status` int(1) DEFAULT 1,
            `created_by` int(11) DEFAULT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 89 Table: orabooks_reimbursements
        $table89 = $wpdb->prefix . 'orabooks_reimbursements';
        $sql89 = "CREATE TABLE {$table89} (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) DEFAULT NULL,
            `employee_id` bigint(20) NOT NULL,
            `reimbursement_no` varchar(50) NOT NULL,
            `date` date NOT NULL,
            `total_amount` decimal(20,4) DEFAULT 0.0000,
            `description` text,
            `status` varchar(20) DEFAULT 'Draft',
            `payment_status` varchar(20) DEFAULT 'Unpaid',
            `approved_by` bigint(20) DEFAULT NULL,
            `approved_at` datetime DEFAULT NULL,
            `paid_by` bigint(20) DEFAULT NULL,
            `paid_at` datetime DEFAULT NULL,
            `created_by` bigint(20) NOT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY employee_id (employee_id),
            KEY status (status)
        ) ENGINE=InnoDB {$charset_collate};";

        // 90 Table: orabooks_reimbursement_items
        $table90 = $wpdb->prefix . 'orabooks_reimbursement_items';
        $sql90 = "CREATE TABLE {$table90} (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `reimbursement_id` bigint(20) NOT NULL,
            `date` date NOT NULL,
            `category_id` int(11) NOT NULL,
            `description` text,
            `amount` decimal(20,4) DEFAULT 0.0000,
            PRIMARY KEY  (id),
            KEY reimbursement_id (reimbursement_id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 91 Table: orabooks_reimbursement_attachments
        $table91 = $wpdb->prefix . 'orabooks_reimbursement_attachments';
        $sql91 = "CREATE TABLE {$table91} (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `reimbursement_id` bigint(20) NOT NULL,
            `item_id` bigint(20) DEFAULT NULL,
            `file_url` text NOT NULL,
            `file_name` varchar(255) DEFAULT NULL,
            `file_type` varchar(100) DEFAULT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY reimbursement_id (reimbursement_id)
        ) ENGINE=InnoDB {$charset_collate};";

        // 92 Table: orabooks_reimbursement_logs
        $table92 = $wpdb->prefix . 'orabooks_reimbursement_logs';
        $sql92 = "CREATE TABLE {$table92} (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `reimbursement_id` bigint(20) NOT NULL,
            `user_id` bigint(20) NOT NULL,
            `action` varchar(50) NOT NULL,
            `note` text,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY reimbursement_id (reimbursement_id)
        ) ENGINE=InnoDB {$charset_collate};";

        // Run dbDelta to safely create or update
        $sql = $sql1 . "\n" . $sql2 . "\n" . $sql3 . "\n" . $sql4 . "\n" . $sql5 . "\n" . $sql6 . "\n" . $sql7 . "\n" . $sql8 . "\n" . $sql9 . "\n" . $sql10 . "\n" .
            $sql11 . "\n" . $sql12 . "\n" . $sql13 . "\n" . $sql14 . "\n" . $sql15 . "\n" . $sql16 . "\n" . $sql17 . "\n" . $sql18 . "\n" . $sql19 . "\n" . $sql20 . "\n" .
            $sql21 . "\n" . $sql22 . "\n" . $sql23 . "\n" . $sql24 . "\n" . $sql25 . "\n" . $sql26 . "\n" . $sql27 . "\n" . $sql_fivemojo . $sql28 . "\n" . $sql29 . "\n" . $sql30 . "\n" .
            $sql31 . "\n" . $sql32 . "\n" . $sql33 . "\n" . $sql34 . "\n" . $sql35 . "\n" . $sql36 . "\n" . $sql37 . "\n" . $sql38 . "\n" . $sql39 . "\n" . $sql40 . "\n" .
            $sql41 . "\n" . $sql42 . "\n" . $sql43 . "\n" . $sql44 . "\n" . $sql45 . "\n" . $sql46 . "\n" . $sql47 . "\n" . $sql48 . "\n" . $sql49 . "\n" . $sql50 . "\n" .
            $sql51 . "\n" . $sql52 . "\n" . $sql53 . "\n" . $sql54 . "\n" . $sql55 . "\n" . $sql56 . "\n" . $sql57 . "\n" . $sql58 . "\n" . $sql59 . "\n" . $sql60 . "\n" .
            $sql61 . "\n" . $sql62 . "\n" . $sql63 . "\n" . $sql64 . "\n" . $sql65 . "\n" . $sql66 . "\n" . $sql67 . "\n" . $sql68 . "\n" . $sql69 . "\n" . $sql70 . "\n" .
            $sql71 . "\n" . $sql72 . "\n" . $sql73 . "\n" . $sql74 . "\n" . $sql75 . "\n" . $sql76 . "\n" . $sql77 . "\n" . $sql78 . "\n" . $sql79 . "\n" . $sql80 . "\n" . $sql81 . "\n" . $sql82 . "\n" . $sql83 . "\n" . $sql84 . "\n" . $sql85 . "\n" . $sql86 . "\n" . $sql87 . "\n" . $sql88 . "\n" . $sql89 . "\n" . $sql90 . "\n" . $sql91 . "\n" . $sql92 . "\n" . $sql_store_init;


        dbDelta($sql);

        // Initial Data for Accounting Module Sidebar - Row by Row to Avoid Duplicates
        $table_sidebar = $wpdb->prefix . 'orabooks_db_sidebar';

        // Robust cleanup: Delete duplicates for accounting module to avoid multiple menus
        $wpdb->query($wpdb->prepare("DELETE t1 FROM $table_sidebar t1 INNER JOIN $table_sidebar t2 WHERE t1.id > t2.id AND t1.menu_slug = t2.menu_slug AND t1.module = t2.module AND t1.parent = t2.parent AND t1.module = %s", 'accounting'));
        

        $default_menus_acc = [
            ["module" => "accounting", "parent" => 0, "menu_title" => "Dashboard", "menu_slug" => "dashboard", "icon" => "fa-solid fa-gauge", "sort_order" => 1, "status" => 1],
            ["module" => "accounting", "parent" => 0, "menu_title" => "All Features", "menu_slug" => "all-features", "icon" => "fa-solid fa-layer-group", "sort_order" => 2, "status" => 1],
            ["module" => "accounting", "parent" => 0, "menu_title" => "Setting", "menu_slug" => "setting", "icon" => "fa-solid fa-gear", "sort_order" => 3, "status" => 1],
            ["module" => "accounting", "parent" => 0, "menu_title" => "Accounts", "menu_slug" => "accounts", "icon" => "fa-solid fa-building-columns", "sort_order" => 4, "status" => 1],
            ["module" => "accounting", "parent" => 0, "menu_title" => "Advance", "menu_slug" => "advance", "icon" => "fa-solid fa-money-bill-transfer", "sort_order" => 5, "status" => 1],
            ["module" => "accounting", "parent" => 0, "menu_title" => "Quotation", "menu_slug" => "quotation", "icon" => "fa-solid fa-file-invoice-dollar", "sort_order" => 6, "status" => 1],
            ["module" => "accounting", "parent" => 0, "menu_title" => "Expense", "menu_slug" => "expense", "icon" => "fa-solid fa-receipt", "sort_order" => 7, "status" => 1],
            ["module" => "accounting", "parent" => 0, "menu_title" => "Coupons", "menu_slug" => "coupons", "icon" => "fa-solid fa-tags", "sort_order" => 8, "status" => 1],
            ["module" => "accounting", "parent" => 0, "menu_title" => "Assets", "menu_slug" => "assets", "icon" => "fa-solid fa-building-circle-check", "sort_order" => 9, "status" => 1],
            ["module" => "accounting", "parent" => 0, "menu_title" => "Reimbursements", "menu_slug" => "reimbursements", "icon" => "fa-solid fa-hand-holding-dollar", "sort_order" => 10, "status" => 1],
            ["module" => "accounting", "parent" => 0, "menu_title" => "Acc. Reports", "menu_slug" => "acc-reports", "icon" => "fa-solid fa-chart-line", "sort_order" => 11, "status" => 1],
        ];

        foreach ($default_menus_acc as $menu) {
            $existing_parent = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table_sidebar WHERE module = %s AND menu_slug = %s AND parent = 0", 'accounting', $menu['menu_slug']));

            if (!$existing_parent) {
                $wpdb->insert($table_sidebar, $menu);
                $parent_id = $wpdb->insert_id;
            } else {
                $parent_id = $existing_parent->id;
            }

            // Submenus Data for Accounting
            $submenus = [];
            if ($menu['menu_title'] == 'Setting') {
                $submenus = [
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Currency", "menu_slug" => "setting-currency-list", "icon" => "fa-solid fa-coins", "sort_order" => 1, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Tax", "menu_slug" => "setting-tax-list", "icon" => "fa-solid fa-percent", "sort_order" => 2, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Payment types", "menu_slug" => "setting-payment-types-list", "icon" => "fa-solid fa-credit-card", "sort_order" => 3, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Add Features", "menu_slug" => "add-features", "icon" => "fa-solid fa-plus-circle", "sort_order" => 4, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "User Permissions", "menu_slug" => "setting-user-permissions", "icon" => "fa-solid fa-user-shield", "sort_order" => 5, "status" => 1],
                ];
            } elseif ($menu['menu_title'] == 'Accounts') {
                $submenus = [
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "View Accounts", "menu_slug" => "accounts", "icon" => "fa-solid fa-list", "sort_order" => 1, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Account Types", "menu_slug" => "coa-types-list", "icon" => "fa-solid fa-layer-group", "sort_order" => 2, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Chart of Accounts", "menu_slug" => "coa-list", "icon" => "fa-solid fa-table-list", "sort_order" => 3, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "View Deposit", "menu_slug" => "accounts-deposit", "icon" => "fa-solid fa-money-bill", "sort_order" => 4, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Money Transfer", "menu_slug" => "money-transfer-list", "icon" => "fa-solid fa-money-bill-transfer", "sort_order" => 5, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Cash Transactions", "menu_slug" => "cash-transactions", "icon" => "fa-solid fa-cash-register", "sort_order" => 6, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Journal Entry", "menu_slug" => "journal-entry-list", "icon" => "fa-solid fa-book-journal-whills", "sort_order" => 7, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Fiscal Year", "menu_slug" => "fiscal-year-list", "icon" => "fa-solid fa-calendar-check", "sort_order" => 8, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Opening Balance", "menu_slug" => "opening-balance-input", "icon" => "fa-solid fa-scale-balanced", "sort_order" => 9, "status" => 1],
                ];
            } elseif ($menu['menu_title'] == 'Advance') {
                $submenus = [
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Add Advance", "menu_slug" => "advance-add", "icon" => "fa-solid fa-plus-circle", "sort_order" => 1, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Advance List", "menu_slug" => "advance-list", "icon" => "fa-solid fa-list-ol", "sort_order" => 2, "status" => 1],
                ];
            } elseif ($menu['menu_title'] == 'Quotation') {
                $submenus = [
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Add Quotation", "menu_slug" => "quotation-add", "icon" => "fa-solid fa-file-circle-plus", "sort_order" => 1, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Quotation List", "menu_slug" => "quotation-list", "icon" => "fa-solid fa-clipboard-list", "sort_order" => 2, "status" => 1],
                ];
            } elseif ($menu['menu_title'] == 'Expense') {
                $submenus = [
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Expense List", "menu_slug" => "expense-list", "icon" => "fa-solid fa-circle-dot", "sort_order" => 1, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Category List", "menu_slug" => "expense-category", "icon" => "fa-solid fa-circle-dot", "sort_order" => 2, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Add Expense", "menu_slug" => "expense-add", "icon" => "fa-solid fa-circle-plus", "sort_order" => 3, "status" => 1],
                ];
            } elseif ($menu['menu_title'] == 'Coupons') {
                $submenus = [
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Customer Coupon List", "menu_slug" => "coupon-customer-list", "icon" => "fa-solid fa-ticket", "sort_order" => 2, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Coupon Master", "menu_slug" => "coupon-master", "icon" => "fa-solid fa-table-list", "sort_order" => 4, "status" => 1],
                ];
            } elseif ($menu['menu_title'] == 'Assets') {
                $submenus = [
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Asset Category", "menu_slug" => "asset-category", "icon" => "fa-solid fa-tags", "sort_order" => 1, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Asset Register", "menu_slug" => "asset-list", "icon" => "fa-solid fa-list-check", "sort_order" => 2, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Add Asset", "menu_slug" => "asset-add", "icon" => "fa-solid fa-plus-circle", "sort_order" => 3, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Disposal Register", "menu_slug" => "asset-disposal-list", "icon" => "fa-solid fa-trash-can", "sort_order" => 4, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Depreciation Method", "menu_slug" => "depreciation-methods", "icon" => "fa-solid fa-calculator", "sort_order" => 5, "status" => 1],
                ];
            } elseif ($menu['menu_title'] == 'Reimbursements') {
                $submenus = [
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "My Reimbursements", "menu_slug" => "reimbursement-list", "icon" => "fa-solid fa-list", "sort_order" => 1, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "New Request", "menu_slug" => "reimbursement-add", "icon" => "fa-solid fa-plus-circle", "sort_order" => 2, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Pending Approvals", "menu_slug" => "reimbursement-approvals", "icon" => "fa-solid fa-user-check", "sort_order" => 3, "status" => 1],
                ];
            } elseif ($menu['menu_title'] == 'Acc. Reports') {
                $submenus = [
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Journal Report", "menu_slug" => "journal-report", "icon" => "fa-solid fa-book", "sort_order" => 1, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Trial Balance", "menu_slug" => "trial-balance-report", "icon" => "fa-solid fa-scale-balanced", "sort_order" => 2, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Income Statement", "menu_slug" => "income-statement-report", "icon" => "fa-solid fa-file-invoice-dollar", "sort_order" => 3, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Balance Sheet", "menu_slug" => "balance-sheet-report", "icon" => "fa-solid fa-scale-unbalanced", "sort_order" => 4, "status" => 1],
                    ["module" => "accounting", "parent" => $parent_id, "menu_title" => "Ledger Report", "menu_slug" => "ledger-report", "icon" => "fa-solid fa-book-journal-whills", "sort_order" => 5, "status" => 1],
                ];
            }

            foreach ($submenus as $submenu) {
                $exists = $wpdb->get_var($wpdb->prepare("SELECT count(*) FROM $table_sidebar WHERE module = %s AND menu_slug = %s AND parent = %d", 'accounting', $submenu['menu_slug'], $parent_id));
                if (!$exists) {
                    $wpdb->insert($table_sidebar, $submenu);
                }
            }
        }

        $table_coa_types = $wpdb->prefix . 'orabooks_ac_coa_types';
        $default_coa_types = ['Assets', 'Liabilities', 'Equity', 'Income', 'Expenses'];

        foreach ($default_coa_types as $type) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_coa_types WHERE coa_type = %s", $type));
            if (!$exists) {
                $wpdb->insert($table_coa_types, ['coa_type' => $type, 'status' => 1]);
            }
        }

        // Default Master Accounts in CoA
        $table_coa_list = $wpdb->prefix . 'orabooks_ac_coa_list';
        $assets_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_coa_types WHERE coa_type = %s", 'Assets'));
        $liabilities_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_coa_types WHERE coa_type = %s", 'Liabilities'));
        $equity_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_coa_types WHERE coa_type = %s", 'Equity'));

        $master_accounts = [
            ['name' => 'Accounts Receivable', 'type' => $assets_id, 'code' => '1100'],
            ['name' => 'Inventory', 'type' => $assets_id, 'code' => '1200'],
            ['name' => 'Accounts Payable', 'type' => $liabilities_id, 'code' => '2100'],
            ['name' => 'Opening Balance Equity', 'type' => $equity_id, 'code' => '3000'],
        ];

        foreach ($master_accounts as $ma) {
            if ($ma['type']) {
                $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_coa_list WHERE account_name = %s", $ma['name']));
                if (!$exists) {
                    $wpdb->insert($table_coa_list, [
                        'coa_type_id' => $ma['type'],
                        'account_code' => $ma['code'],
                        'account_name' => $ma['name'],
                        'status' => 1
                    ]);
                }
            }
        }

        // Initialize Admin Role and Accounting Permissions for Main User
        $table_roles = $wpdb->prefix . 'orabooks_db_roles';
        $table_permissions = $wpdb->prefix . 'orabooks_db_permissions';

        $admin_role = $wpdb->get_row("SELECT * FROM $table_roles WHERE role_name = 'Admin'");
        if (!$admin_role) {
            $wpdb->insert($table_roles, array(
                'role_name' => 'Admin',
                'description' => 'System Administrator',
                'status' => 1
            ));
            $role_id = $wpdb->insert_id;
        } else {
            $role_id = $admin_role->id;
        }

        // Fetch all accounting menu slugs from sidebar table
        $accounting_slugs = $wpdb->get_col($wpdb->prepare("SELECT menu_slug FROM $table_sidebar WHERE module = %s", 'accounting'));

        if (!empty($accounting_slugs)) {
            $existing_permission = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_permissions WHERE role_id = %d", $role_id));
            $permissions_json = json_encode(array_values($accounting_slugs));

            if (!$existing_permission) {
                $wpdb->insert($table_permissions, array(
                    'role_id' => $role_id,
                    'permissions' => $permissions_json
                ));
            } else {
                // Update to merge with any existing permissions
                $existing_slugs = json_decode($existing_permission->permissions, true);
                if (!is_array($existing_slugs))
                    $existing_slugs = [];
                $merged_slugs = array_values(array_unique(array_merge($existing_slugs, $accounting_slugs)));

                $wpdb->update(
                    $table_permissions,
                    array('permissions' => json_encode($merged_slugs)),
                    array('id' => $existing_permission->id)
                );
            }
        }

        // Initialize Default Asset Categories
        $table_asset_cat = $wpdb->prefix . 'orabooks_ac_asset_category';
        $default_categories = [
            ['category_code' => 'FIXED_ASSET', 'category_name' => 'Fixed Asset', 'description' => 'Fixed assets including buildings, machinery, equipment, and other long-term tangible assets', 'default_useful_life_years' => 10],
            ['category_code' => 'COMP_EQUIP', 'category_name' => 'Computers & Equipment', 'description' => 'Desktop computers, laptops, servers, and related equipment', 'default_useful_life_years' => 5],
            ['category_code' => 'FURN_FIX', 'category_name' => 'Furniture & Fixtures', 'description' => 'Office furniture, fixtures, and fittings', 'default_useful_life_years' => 7],
            ['category_code' => 'VEHICLES', 'category_name' => 'Vehicles', 'description' => 'Cars, trucks, and other vehicles', 'default_useful_life_years' => 5],
            ['category_code' => 'MACHINERY', 'category_name' => 'Machinery', 'description' => 'Industrial machinery and equipment', 'default_useful_life_years' => 10],
            ['category_code' => 'BUILDINGS', 'category_name' => 'Buildings', 'description' => 'Buildings and structures', 'default_useful_life_years' => 20],
            ['category_code' => 'LAND', 'category_name' => 'Land', 'description' => 'Land and land improvements', 'default_useful_life_years' => NULL],
            ['category_code' => 'SOFTWARE', 'category_name' => 'Software', 'description' => 'Computer software and licenses', 'default_useful_life_years' => 3],
            ['category_code' => 'OTHER_EQUIP', 'category_name' => 'Other Equipment', 'description' => 'Other types of equipment not classified elsewhere', 'default_useful_life_years' => 5]
        ];

        foreach ($default_categories as $cat) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_asset_cat WHERE category_name = %s", $cat['category_name']));
            if ($exists == 0) {
                $wpdb->insert($table_asset_cat, [
                    'category_code' => $cat['category_code'],
                    'category_name' => $cat['category_name'],
                    'description' => $cat['description'],
                    'depreciation_method' => 'straight_line',
                    'default_useful_life_years' => $cat['default_useful_life_years'],
                    'created_by' => get_current_user_id(),
                    'created_date' => current_time('Y-m-d'),
                    'created_time' => current_time('H:i:s'),
                    'system_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'system_name' => gethostname(),
                    'status' => 1
                ]);
            }
        }

        $output = ob_get_clean();
        if (!empty($output)) {
            error_log("OBN Accounting Activation Output: " . $output);
        }
    }

}
