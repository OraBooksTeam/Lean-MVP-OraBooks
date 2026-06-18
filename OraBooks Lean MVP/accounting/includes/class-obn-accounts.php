<?php
/**
 * Frontend-Accounting Accounts handlers (adapted from Orabooks Accounts)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class OBN_Accounts {

    public function __construct() {
        add_action('wp_ajax_obn_insert_account', array( $this, 'insert_account' ) );
        add_action('wp_ajax_nopriv_obn_insert_account', array( $this, 'insert_account' ) );
        
        add_action('wp_ajax_obn_update_account', array( $this, 'update_account' ) );
        add_action('wp_ajax_nopriv_obn_update_account', array( $this, 'update_account' ) );
        
        add_action('wp_ajax_obn_delete_account', array( $this, 'delete_account' ) );
        add_action('wp_ajax_nopriv_obn_delete_account', array( $this, 'delete_account' ) );
        
        add_action('wp_ajax_obn_toggle_accounts_status', array( $this, 'toggle_status' ) );
        add_action('wp_ajax_nopriv_obn_toggle_accounts_status', array( $this, 'toggle_status' ) );

        // CoA Types
        add_action('wp_ajax_obn_insert_coa_type', array( $this, 'insert_coa_type' ) );
        add_action('wp_ajax_nopriv_obn_insert_coa_type', array( $this, 'insert_coa_type' ) );
        add_action('wp_ajax_obn_update_coa_type', array( $this, 'update_coa_type' ) );
        add_action('wp_ajax_nopriv_obn_update_coa_type', array( $this, 'update_coa_type' ) );
        add_action('wp_ajax_obn_delete_coa_type', array( $this, 'delete_coa_type' ) );
        add_action('wp_ajax_nopriv_obn_delete_coa_type', array( $this, 'delete_coa_type' ) );
        add_action('wp_ajax_obn_toggle_coa_type_status', array( $this, 'toggle_coa_type_status' ) );
        add_action('wp_ajax_nopriv_obn_toggle_coa_type_status', array( $this, 'toggle_coa_type_status' ) );

        // CoA List
        add_action('wp_ajax_obn_insert_coa', array( $this, 'insert_coa' ) );
        add_action('wp_ajax_nopriv_obn_insert_coa', array( $this, 'insert_coa' ) );
        add_action('wp_ajax_obn_update_coa', array( $this, 'update_coa' ) );
        add_action('wp_ajax_nopriv_obn_update_coa', array( $this, 'update_coa' ) );
        add_action('wp_ajax_obn_delete_coa', array( $this, 'delete_coa' ) );
        add_action('wp_ajax_nopriv_obn_delete_coa', array( $this, 'delete_coa' ) );
        add_action('wp_ajax_obn_toggle_coa_status', array( $this, 'toggle_coa_status' ) );
        add_action('wp_ajax_nopriv_obn_toggle_coa_status', array( $this, 'toggle_coa_status' ) );

        // Frontend (nonce: frontend_ajax_nonce) for Add Sale page quick-add
        add_action('wp_ajax_frontend_insert_coa', array( $this, 'frontend_insert_coa' ) );
        add_action('wp_ajax_nopriv_frontend_insert_coa', array( $this, 'frontend_insert_coa' ) );

        // Frontend: Insert Account (Bank Account) for Add Sale page
        add_action('wp_ajax_frontend_insert_account', array( $this, 'frontend_insert_account' ) );
        add_action('wp_ajax_nopriv_frontend_insert_account', array( $this, 'frontend_insert_account' ) );
    }

    public static function insert_account() {
        check_ajax_referer('obn_accounts_action_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_accounts';

        $parent_account = intval( $_POST['parent_account'] ?? 0 );
        $account_code = sanitize_text_field( $_POST['account_code'] ?? '' );
        $account_name = sanitize_text_field( $_POST['account_name'] ?? '' );
        $opening_balance = floatval( $_POST['opening_balance'] ?? 0 );
        $note = sanitize_textarea_field( $_POST['note'] ?? '' );

        if ( empty( $account_code ) || empty( $account_name ) || ! is_numeric( $opening_balance ) ) {
            wp_send_json_error('All required fields must be provided.');
        }

        $system_ip = sanitize_text_field( getHostByName(getHostName()) );
        $system_name = sanitize_text_field( gethostname() );

        $inserted = $wpdb->insert( $table, array(
            'parent_id' => $parent_account,
            'account_name' => $account_name,
            'account_code' => $account_code,
            'balance' => $opening_balance,
            'note' => $note,
            'created_date' => date('Y-m-d'),
            'created_time' => current_time('mysql'),
            'system_ip' => $system_ip,
            'system_name' => $system_name,
            'status' => 1,
        ), array( '%d','%s','%s','%f','%s','%s','%s','%s','%s','%d' ) );

        if ( $inserted ) {
            wp_send_json_success( array( 'message' => 'Account added successfully.' ) );
        } else {
            wp_send_json_error('Failed to insert account.');
        }
    }

    public static function update_account() {
        check_ajax_referer('obn_accounts_action_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_accounts';

        $id = intval( $_POST['id'] ?? 0 );
        $parent_account = intval( $_POST['parent_account'] ?? 0 );
        $account_code = sanitize_text_field( $_POST['account_code'] ?? '' );
        $account_name = sanitize_text_field( $_POST['account_name'] ?? '' );
        $opening_balance = floatval( $_POST['opening_balance'] ?? 0 );
        $note = sanitize_textarea_field( $_POST['note'] ?? '' );

        if ( $id <= 0 || empty( $account_code ) || empty( $account_name ) || ! is_numeric( $opening_balance ) ) {
            wp_send_json_error('All required fields must be provided.');
        }

        $updated = $wpdb->update( $table, array(
            'parent_id' => $parent_account,
            'account_name' => $account_name,
            'account_code' => $account_code,
            'balance' => $opening_balance,
            'note' => $note,
        ), array( 'id' => $id ), array( '%d','%s','%s','%f','%s' ), array( '%d' ) );

        if ( $updated !== false ) {
            wp_send_json_success( array( 'message' => 'Account updated successfully.' ) );
        } else {
            wp_send_json_error('Failed to update account.');
        }
    }

    public static function delete_account() {
        check_ajax_referer('obn_accounts_action_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_accounts';
        $id = intval( $_POST['id'] ?? 0 );

        if ( $id <= 0 ) wp_send_json_error('Invalid ID.');

        $deleted = $wpdb->delete( $table, array( 'id' => $id ) );

        if ( $deleted ) {
            wp_send_json_success( array( 'message' => 'Account deleted successfully.' ) );
        } else {
            wp_send_json_error('Failed to delete account.');
        }
    }

    public static function toggle_status() {
        check_ajax_referer('obn_accounts_action_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_accounts';

        $id = intval( $_POST['id'] ?? 0 );
        $status = intval( $_POST['status'] ?? 0 );

        if ( $id <= 0 ) wp_send_json_error('Invalid ID.');

        $updated = $wpdb->update( $table, array( 'status' => $status ), array( 'id' => $id ) );

        if ( $updated !== false ) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to update status.');
        }
    }

    // --- CoA Types (Account Types) Handlers ---

    public static function insert_coa_type() {
        check_ajax_referer('obn_accounts_action_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_coa_types';

        $coa_types    = isset( $_POST['coa_type'] )    ? (array) $_POST['coa_type']    : array();
        $descriptions = isset( $_POST['description'] ) ? (array) $_POST['description'] : array();

        if ( empty( $coa_types ) ) {
            wp_send_json_error('At least one Account Type row is required.');
        }

        $inserted_count = 0;
        $last_insert_id  = null;
        $last_type_name  = '';
        $skipped         = array();
        $errors          = array();

        foreach ( $coa_types as $index => $raw_type ) {
            $coa_type    = sanitize_text_field( $raw_type );
            $description = sanitize_textarea_field( $descriptions[ $index ] ?? '' );

            if ( empty( $coa_type ) ) {
                $errors[] = 'Row ' . ( $index + 1 ) . ': Account Type name is required.';
                continue;
            }

            // Duplicate check
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE coa_type = %s", $coa_type
            ) );
            if ( $exists > 0 ) {
                $skipped[] = $coa_type;
                continue;
            }

            $result = $wpdb->insert( $table, array(
                'coa_type'    => $coa_type,
                'description' => $description,
                'status'      => 1,
            ), array( '%s', '%s', '%d' ) );

            if ( $result ) {
                $inserted_count++;
                $last_insert_id = $wpdb->insert_id;
                $last_type_name = $coa_type;
            } else {
                $errors[] = 'Row ' . ( $index + 1 ) . ': Database insert failed for "' . $coa_type . '".';
            }
        }

        if ( $inserted_count === 0 && empty( $errors ) && ! empty( $skipped ) ) {
            wp_send_json_error( 'All entries already exist: ' . implode( ', ', $skipped ) );
        }

        $msg = $inserted_count . ' account type' . ( $inserted_count !== 1 ? 's' : '' ) . ' added successfully.';
        if ( ! empty( $skipped ) ) {
            $msg .= ' Skipped (duplicates): ' . implode( ', ', $skipped ) . '.';
        }
        if ( ! empty( $errors ) ) {
            $msg .= ' Errors: ' . implode( ' | ', $errors );
        }

        if ( $inserted_count > 0 ) {
            wp_send_json_success( array(
                'message' => $msg,
                'id'      => (int) $last_insert_id,
                'name'    => $last_type_name,
            ) );
        } else {
            wp_send_json_error( $msg );
        }
    }

    public static function update_coa_type() {
        check_ajax_referer('obn_accounts_action_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_coa_types';

        $id = intval( $_POST['id'] ?? 0 );
        $coa_type = sanitize_text_field( $_POST['coa_type'] ?? '' );
        $description = sanitize_textarea_field( $_POST['description'] ?? '' );

        if ( $id <= 0 || empty( $coa_type ) ) {
            wp_send_json_error('All required fields must be provided.');
        }

        $updated = $wpdb->update( $table, array(
            'coa_type' => $coa_type,
            'description' => $description,
        ), array( 'id' => $id ), array( '%s','%s' ), array( '%d' ) );

        if ( $updated !== false ) {
            wp_send_json_success( array( 'message' => 'Account Type updated successfully.' ) );
        } else {
            wp_send_json_error('Failed to update account type.');
        }
    }

    public static function delete_coa_type() {
        check_ajax_referer('obn_accounts_action_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_coa_types';
        $id = intval( $_POST['id'] ?? 0 );

        if ( $id <= 0 ) wp_send_json_error('Invalid ID.');

        $deleted = $wpdb->delete( $table, array( 'id' => $id ) );

        if ( $deleted ) {
            wp_send_json_success( array( 'message' => 'Account Type deleted successfully.' ) );
        } else {
            wp_send_json_error('Failed to delete account type.');
        }
    }

    public static function toggle_coa_type_status() {
        check_ajax_referer('obn_accounts_action_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_coa_types';

        $id = intval( $_POST['id'] ?? 0 );
        $status = intval( $_POST['status'] ?? 0 );

        if ( $id <= 0 ) wp_send_json_error('Invalid ID.');

        $updated = $wpdb->update( $table, array( 'status' => $status ), array( 'id' => $id ) );

        if ( $updated !== false ) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to update status.');
        }
    }

    // --- CoA List (Chart of Accounts) Handlers ---

    public static function insert_coa() {
        check_ajax_referer('obn_accounts_action_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_coa_list';

        $coa_type_id = intval( $_POST['coa_type_id'] ?? 0 );
        $account_code = sanitize_text_field( $_POST['account_code'] ?? '' );
        $account_name = sanitize_text_field( $_POST['account_name'] ?? '' );
        $description = sanitize_textarea_field( $_POST['description'] ?? '' );
        $tax_id = !empty($_POST['tax_id']) ? intval($_POST['tax_id']) : null;

        if ( empty( $account_code ) || empty( $account_name ) || $coa_type_id <= 0 ) {
            wp_send_json_error('Account Type, Code, and Name are required.');
        }

        // Check for unique code
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE account_code = %s", $account_code ) );
        if ( $exists ) {
            wp_send_json_error('Account Code already exists.');
        }

        $inserted = $wpdb->insert( $table, array(
            'coa_type_id' => $coa_type_id,
            'account_code' => $account_code,
            'account_name' => $account_name,
            'description' => $description,
            'tax_id' => $tax_id,
            'status' => 1,
        ), array( '%d','%s','%s','%s','%d','%d' ) );

        if ( $inserted ) {
            wp_send_json_success( array( 
                'message' => 'Account added to CoA successfully.',
                'id' => $wpdb->insert_id,
                'account_code' => $account_code,
                'account_name' => $account_name
            ) );
        } else {
            wp_send_json_error('Failed to insert account.');
        }
    }

    public static function update_coa() {
        check_ajax_referer('obn_accounts_action_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_coa_list';

        $id = intval( $_POST['id'] ?? 0 );
        $coa_type_id = intval( $_POST['coa_type_id'] ?? 0 );
        $account_code = sanitize_text_field( $_POST['account_code'] ?? '' );
        $account_name = sanitize_text_field( $_POST['account_name'] ?? '' );
        $description = sanitize_textarea_field( $_POST['description'] ?? '' );
        $tax_id = !empty($_POST['tax_id']) ? intval($_POST['tax_id']) : null;

        if ( $id <= 0 || empty( $account_code ) || empty( $account_name ) || $coa_type_id <= 0 ) {
            wp_send_json_error('All required fields must be provided.');
        }

        // Check for unique code excluding self
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE account_code = %s AND id != %d", $account_code, $id ) );
        if ( $exists ) {
            wp_send_json_error('Account Code already exists.');
        }

        $updated = $wpdb->update( $table, array(
            'coa_type_id' => $coa_type_id,
            'account_code' => $account_code,
            'account_name' => $account_name,
            'description' => $description,
            'tax_id' => $tax_id,
        ), array( 'id' => $id ), array( '%d','%s','%s','%s','%d' ), array( '%d' ) );

        if ( $updated !== false ) {
            wp_send_json_success( array( 'message' => 'Account updated successfully.' ) );
        } else {
            wp_send_json_error('Failed to update account.');
        }
    }

    public static function delete_coa() {
        check_ajax_referer('obn_accounts_action_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_coa_list';
        $id = intval( $_POST['id'] ?? 0 );

        if ( $id <= 0 ) wp_send_json_error('Invalid ID.');

        $deleted = $wpdb->delete( $table, array( 'id' => $id ) );

        if ( $deleted ) {
            wp_send_json_success( array( 'message' => 'Account deleted successfully.' ) );
        } else {
            wp_send_json_error('Failed to delete account.');
        }
    }

    public static function toggle_coa_status() {
        check_ajax_referer('obn_accounts_action_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_coa_list';

        $id = intval( $_POST['id'] ?? 0 );
        $status = intval( $_POST['status'] ?? 0 );

        if ( $id <= 0 ) wp_send_json_error('Invalid ID.');

        $updated = $wpdb->update( $table, array( 'status' => $status ), array( 'id' => $id ) );

        if ( $updated !== false ) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to update status.');
        }
    }

    /**
     * Frontend AJAX: Insert a single COA account (uses frontend_ajax_nonce).
     * Used by the Add Sale page quick-add modal.
     */
    public static function frontend_insert_coa() {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table      = $wpdb->prefix . 'orabooks_ac_coa_list';
        $types_tbl  = $wpdb->prefix . 'orabooks_ac_coa_types';

        $coa_type_id  = intval( $_POST['coa_type_id']  ?? 0 );
        $account_code = sanitize_text_field( trim( $_POST['account_code'] ?? '' ) );
        $account_name = sanitize_text_field( trim( $_POST['account_name'] ?? '' ) );
        $description  = sanitize_textarea_field( $_POST['description'] ?? '' );

        if ( empty( $account_code ) || empty( $account_name ) || $coa_type_id <= 0 ) {
            wp_send_json_error('Account Type, Code, and Name are required.');
        }

        // Check for unique code
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE account_code = %s", $account_code ) );
        if ( $exists ) {
            wp_send_json_error('Account Code already exists. Please use a different code.');
        }

        $inserted = $wpdb->insert( $table, array(
            'coa_type_id'  => $coa_type_id,
            'account_code' => $account_code,
            'account_name' => $account_name,
            'description'  => $description,
            'status'       => 1,
        ), array( '%d', '%s', '%s', '%s', '%d' ) );

        if ( $inserted ) {
            $new_id = $wpdb->insert_id;
            wp_send_json_success( array(
                'id'           => $new_id,
                'account_code' => $account_code,
                'account_name' => $account_name,
                'label'        => $account_code . ' - ' . $account_name,
                'message'      => 'Account added successfully.',
            ) );
        } else {
            wp_send_json_error('Failed to add account.');
        }
    }

    /**
     * Frontend AJAX: Insert a bank account into orabooks_ac_accounts (uses frontend_ajax_nonce).
     * Used by the Add Sale page "Add Bank Account" modal.
     */
    public static function frontend_insert_account() {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_accounts';

        $parent_account = intval( $_POST['parent_account'] ?? 0 );
        $account_code   = sanitize_text_field( trim( $_POST['account_code'] ?? '' ) );
        $account_name   = sanitize_text_field( trim( $_POST['account_name'] ?? '' ) );
        $opening_balance = floatval( $_POST['opening_balance'] ?? 0 );
        $note           = sanitize_textarea_field( $_POST['note'] ?? '' );

        if ( empty( $account_code ) || empty( $account_name ) ) {
            wp_send_json_error('Account Code and Name are required.');
        }

        // Check for unique code
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE account_code = %s", $account_code ) );
        if ( $exists ) {
            wp_send_json_error('Account Code already exists. Please use a different code.');
        }

        $system_ip   = sanitize_text_field( getHostByName(getHostName()) );
        $system_name = sanitize_text_field( gethostname() );

        $inserted = $wpdb->insert( $table, array(
            'parent_id'    => $parent_account,
            'account_name' => $account_name,
            'account_code' => $account_code,
            'balance'      => $opening_balance,
            'note'         => $note,
            'created_date' => current_time('Y-m-d'),
            'created_time' => current_time('mysql'),
            'system_ip'    => $system_ip,
            'system_name'  => $system_name,
            'status'       => 1,
        ), array( '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%d' ) );

        if ( $inserted ) {
            $new_id = $wpdb->insert_id;
            wp_send_json_success( array(
                'id'           => $new_id,
                'account_code' => $account_code,
                'account_name' => $account_name,
                'message'      => 'Bank account added successfully.',
            ) );
        } else {
            wp_send_json_error('Failed to add bank account.');
        }
    }

}

// instantiate to register actions
new OBN_Accounts();
