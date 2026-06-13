<?php
/**
 * Asset Management System Service Layer
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class OBN_Assets {

    public function __construct() {
        // Assets CRUD
        add_action('wp_ajax_obn_insert_asset', array( $this, 'insert_asset' ) );
        add_action('wp_ajax_obn_update_asset', array( $this, 'update_asset' ) );
        add_action('wp_ajax_obn_delete_asset', array( $this, 'delete_asset' ) );
        add_action('wp_ajax_obn_get_asset', array( $this, 'get_asset' ) );
        add_action('wp_ajax_obn_sync_asset_journal', array( $this, 'ajax_sync_asset_journal' ) );

        // Asset Lifecycle
        add_action('wp_ajax_obn_run_depreciation', array( $this, 'run_depreciation' ) );
        add_action('wp_ajax_obn_dispose_asset', array( $this, 'dispose_asset' ) );

        // Depreciation Methods
        add_action('wp_ajax_obn_insert_depr_method', array( $this, 'insert_depr_method' ) );
        
        // Asset Categories
        add_action('wp_ajax_obn_manage_asset_category', array( $this, 'manage_asset_category' ) );
        add_action('wp_ajax_obn_get_asset_category', array( $this, 'get_asset_category' ) );
        add_action('wp_ajax_obn_toggle_asset_category_status', array( $this, 'toggle_asset_category_status' ) );
        add_action('wp_ajax_obn_delete_asset_category', array( $this, 'delete_asset_category' ) );
    }

    public function insert_asset() {
        check_ajax_referer('obn_assets_action_nonce', 'security');
        
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_assets';

        $data = array(
            'store_id' => get_current_blog_id(), // Or however store_id is handled
            'name' => sanitize_text_field($_POST['name']),
            'category' => sanitize_text_field($_POST['category']),
            'purchase_date' => sanitize_text_field($_POST['purchase_date']),
            'cost' => floatval($_POST['cost']),
            'salvage_value' => floatval($_POST['salvage_value']),
            'useful_life_years' => intval($_POST['useful_life_years']),
            'depreciation_method' => sanitize_text_field($_POST['depreciation_method'] ?? 'straight_line'),
            'asset_account_id' => intval($_POST['asset_account_id']),
            'depr_expense_account_id' => intval($_POST['depr_expense_account_id']),
            'accum_depr_account_id' => intval($_POST['accum_depr_account_id']),
            'payment_type_id' => !empty($_POST['payment_type_id']) ? intval($_POST['payment_type_id']) : null,
            'bank_account_id' => !empty($_POST['bank_account_id']) ? intval($_POST['bank_account_id']) : null,
            'status' => 'Active',
            'created_by' => get_current_user_id()
        );

        $inserted = $wpdb->insert($table, $data);

        if ($inserted) {
            $asset_id = $wpdb->insert_id;
            
            // Create automatic journal entry for the asset
            $je_result = $this->sync_journal_entry($asset_id);
            
            if ($je_result) {
                wp_send_json_success(array('message' => 'Asset created successfully with journal entry.'));
            } else {
                // Asset created but journal entry failed - still success but with warning
                error_log("Asset created but journal entry failed for asset ID: $asset_id");
                wp_send_json_success(array('message' => 'Asset created successfully. Warning: Journal entry creation failed.'));
            }
        }
        wp_send_json_error('Failed to create asset.');
    }

    public function run_depreciation() {
        check_ajax_referer('obn_assets_action_nonce', 'security');
        
        global $wpdb;
        $asset_id = intval($_POST['asset_id']);
        $period_date = sanitize_text_field($_POST['period_date']); // e.g. '2023-10-31'

        $asset = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_ac_assets WHERE id = %d", $asset_id));

        if (!$asset || $asset->status !== 'Active') {
            wp_send_json_error('Invalid or inactive asset.');
        }

        // Check if depreciation already exists for this period
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT count(*) FROM {$wpdb->prefix}orabooks_ac_depreciation_records WHERE asset_id = %d AND period_date = %s",
            $asset_id, $period_date
        ));
        if ($exists) {
            wp_send_json_error('Depreciation already recorded for this period.');
        }

        // Calculate Monthly Depreciation (Straight Line)
        $cost = floatval($asset->cost);
        $salvage = floatval($asset->salvage_value);
        $life_months = intval($asset->useful_life_years) * 12;
        
        $monthly_depr = ($cost - $salvage) / $life_months;

        // Get total accumulated so far
        $accumulated = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SUM(depreciation_amount) FROM {$wpdb->prefix}orabooks_ac_depreciation_records WHERE asset_id = %d",
            $asset_id
        )));

        // Prevent over-depreciation
        if ($accumulated + $monthly_depr > ($cost - $salvage)) {
            $monthly_depr = ($cost - $salvage) - $accumulated;
        }

        if ($monthly_depr <= 0) {
            wp_send_json_error('Asset is already fully depreciated.');
        }

        // 1. Create Journal Entry
        $je_id = $this->create_journal_entry(array(
            'entry_date' => $period_date,
            'source_type' => 'asset',
            'source_id' => $asset_id,
            'reference_no' => 'DEPR-' . $asset_id . '-' . date('Ym', strtotime($period_date)),
            'description' => "Monthly depreciation for: " . $asset->name,
            'lines' => array(
                array(
                    'account_id' => $asset->depr_expense_account_id,
                    'debit' => $monthly_depr,
                    'credit' => 0,
                    'description' => 'Depreciation Expense'
                ),
                array(
                    'account_id' => $asset->accum_depr_account_id,
                    'debit' => 0,
                    'credit' => $monthly_depr,
                    'description' => 'Accumulated Depreciation'
                )
            )
        ));

        if (!$je_id) {
            wp_send_json_error('Failed to create journal entry.');
        }

        // 2. Record Depreciation Record
        $wpdb->insert($wpdb->prefix . 'orabooks_ac_depreciation_records', array(
            'asset_id' => $asset_id,
            'period_date' => $period_date,
            'depreciation_amount' => $monthly_depr,
            'accumulated_amount' => $accumulated + $monthly_depr,
            'journal_entry_id' => $je_id
        ));

        wp_send_json_success(array('message' => 'Depreciation processed successfully.', 'amount' => $monthly_depr));
    }

    public function dispose_asset() {
        check_ajax_referer('obn_assets_action_nonce', 'security');

        global $wpdb;
        $asset_id = intval($_POST['asset_id']);
        $disposal_date = sanitize_text_field($_POST['disposal_date']);
        $sale_price = floatval($_POST['sale_price']);
        $note = sanitize_text_field($_POST['note']);
        $cash_account_id = intval($_POST['cash_account_id']);
        $gain_loss_account_id = intval($_POST['gain_loss_account_id']);

        $asset = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_ac_assets WHERE id = %d", $asset_id));

        if (!$asset || $asset->status !== 'Active') {
            wp_send_json_error('Invalid or inactive asset.');
        }

        // Calculate NBV
        $accumulated = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SUM(depreciation_amount) FROM {$wpdb->prefix}orabooks_ac_depreciation_records WHERE asset_id = %d",
            $asset_id
        )));
        $nbv = $asset->cost - $accumulated;
        $gain_loss = $sale_price - $nbv;

        // Create Journal Entry for Disposal
        // 1. Debit Cash/Bank for sale price
        // 2. Debit Accumulated Depreciation for total accum
        // 3. Credit Asset Cost for full cost
        // 4. Debit/Credit Gain/Loss
        
        $lines = array(
            array(
                'account_id' => $cash_account_id,
                'debit' => $sale_price,
                'credit' => 0,
                'description' => 'Disposal Proceeds'
            ),
            array(
                'account_id' => $asset->accum_depr_account_id,
                'debit' => $accumulated,
                'credit' => 0,
                'description' => 'Reversal of Accumulated Depreciation'
            ),
            array(
                'account_id' => $asset->asset_account_id,
                'debit' => 0,
                'credit' => $asset->cost,
                'description' => 'Remove Asset Cost'
            )
        );

        if ($gain_loss > 0) {
            // Gain (Credit)
            $lines[] = array(
                'account_id' => $gain_loss_account_id,
                'debit' => 0,
                'credit' => $gain_loss,
                'description' => 'Gain on Sale of Asset'
            );
        } elseif ($gain_loss < 0) {
            // Loss (Debit)
            $lines[] = array(
                'account_id' => $gain_loss_account_id,
                'debit' => abs($gain_loss),
                'credit' => 0,
                'description' => 'Loss on Sale of Asset'
            );
        }

        $je_id = $this->create_journal_entry(array(
            'entry_date' => $disposal_date,
            'source_type' => 'asset',
            'source_id' => $asset_id,
            'reference_no' => 'DISP-' . $asset_id,
            'description' => "Asset Disposal: " . $asset->name,
            'lines' => $lines
        ));

        if ($je_id) {
            // Record Disposal
            $wpdb->insert($wpdb->prefix . 'orabooks_ac_asset_disposals', array(
                'asset_id' => $asset_id,
                'disposal_date' => $disposal_date,
                'sale_price' => $sale_price,
                'gain_loss' => $gain_loss,
                'journal_entry_id' => $je_id,
                'note' => $note
            ));

            // Mark asset as Disposed
            $wpdb->update($wpdb->prefix . 'orabooks_ac_assets', array('status' => 'Disposed'), array('id' => $asset_id));

            wp_send_json_success(array('message' => 'Asset disposed successfully. Balance Sheet adjusted.'));
        }

        wp_send_json_error('Failed to process disposal.');
    }

    private function create_journal_entry($params) {
        global $wpdb;
        $je_table = $wpdb->prefix . 'orabooks_ac_journal_entry';
        $je_line_table = $wpdb->prefix . 'orabooks_ac_journal_line';

        $total_debit = 0;
        foreach($params['lines'] as $line) {
            $total_debit += $line['debit'];
        }

        $inserted = $wpdb->insert($je_table, array(
            'store_id' => get_current_blog_id(),
            'entry_date' => $params['entry_date'],
            'source_type' => $params['source_type'],
            'source_id' => $params['source_id'],
            'reference_no' => $params['reference_no'],
            'description' => $params['description'],
            'total_debit' => $total_debit,
            'total_credit' => $total_debit, // Balanced
            'status' => 'Posted',
            'created_by' => get_current_user_id()
        ));

        if (!$inserted) return false;

        $je_id = $wpdb->insert_id;

        foreach($params['lines'] as $idx => $l) {
            $wpdb->insert($je_line_table, array(
                'journal_entry_id' => $je_id,
                'account_id' => $l['account_id'],
                'debit' => $l['debit'],
                'credit' => $l['credit'],
                'debit_amt' => $l['debit'],
                'credit_amt' => $l['credit'],
                'description' => $l['description'],
                'line_number' => $idx + 1
            ));
        }

        return $je_id;
    }

    /**
     * Sync Journal Entry for Fixed Asset Creation
     * Creates automatic journal entry when fixed asset is created/updated
     */
    public function sync_journal_entry($asset_id) {
        global $wpdb;
        
        // Validate asset ID
        if (empty($asset_id) || !is_numeric($asset_id)) {
            return false;
        }

        // Step 1: Get fixed asset data
        $asset_table = $wpdb->prefix . 'orabooks_ac_assets';
        $asset = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $asset_table WHERE id = %d",
            $asset_id
        ));

        if (!$asset) {
            error_log("Asset not found for ID: $asset_id");
            return false;
        }

        // Validate required fields
        if (empty($asset->asset_account_id) || empty($asset->cost) || $asset->cost <= 0) {
            error_log("Invalid asset data for journal entry - missing account ID or cost");
            return false;
        }

        // Step 2: Clean up existing journal entries for this asset
        $this->cleanup_asset_journal_entries($asset_id);

        // Step 3: Get payment type details
        $payment_type_name = null;
        if (!empty($asset->payment_type_id)) {
            $paymenttypes_table = $wpdb->prefix . 'orabooks_db_paymenttypes';
            $payment_type = $wpdb->get_row($wpdb->prepare(
                "SELECT payment_type FROM $paymenttypes_table WHERE id = %d",
                $asset->payment_type_id
            ));
            $payment_type_name = $payment_type ? $payment_type->payment_type : null;
        }

        // Step 4: Determine payment account dynamically
        $payment_account_id = $this->get_payment_account_id($payment_type_name, $asset->bank_account_id);
        
        if (!$payment_account_id) {
            error_log("Could not determine payment account for asset ID: $asset_id");
            return false;
        }

        // Step 5: Create journal entry lines
        $lines = array();
        
        // Debit Fixed Asset Account
        $lines[] = array(
            'account_id' => $asset->asset_account_id,
            'debit' => floatval($asset->cost),
            'credit' => 0,
            'description' => 'Asset Acquisition: ' . $asset->name
        );

        // Credit Payment Account (Cash/Bank)
        $lines[] = array(
            'account_id' => $payment_account_id,
            'debit' => 0,
            'credit' => floatval($asset->cost),
            'description' => 'Payment for Asset: ' . $asset->name
        );

        // Step 6: Create journal entry
        $journal_data = array(
            'entry_date' => $asset->purchase_date,
            'source_type' => 'fixed_asset',
            'source_id' => $asset_id,
            'reference_no' => 'ASSET-' . $asset_id . '-' . date('Ym', strtotime($asset->purchase_date)),
            'description' => "Fixed Asset Acquisition: " . $asset->name,
            'lines' => $lines
        );

        $je_id = $this->create_journal_entry($journal_data);
        
        if ($je_id) {
            // Update asset record with journal entry ID
            $wpdb->update(
                $asset_table,
                array('journal_entry_id' => $je_id),
                array('id' => $asset_id),
                array('%d'),
                array('%d')
            );
            
            error_log("Journal entry created successfully for asset ID: $asset_id, JE ID: $je_id");
            return $je_id;
        } else {
            error_log("Failed to create journal entry for asset ID: $asset_id");
            return false;
        }
    }

    /**
     * Get Payment Account ID based on payment type (matches expense method logic)
     */
    private function get_payment_account_id($payment_type_name, $bank_account_id = null) {
        global $wpdb;
        
        $coa_table = $wpdb->prefix . 'orabooks_ac_coa_list';
        
        // Default account search keyword (matches expense method)
        $account_keyword = 'Cash';

        // Step 1: Get payment type record (matches expense method)
        if ($payment_type_name) {
            $payment_type_record = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * 
                     FROM {$wpdb->prefix}orabooks_db_paymenttypes 
                     WHERE payment_type = %s 
                     LIMIT 1",
                    $payment_type_name
                )
            );

            // Step 2: Determine account keyword (matches expense method)
            if ($payment_type_record) {
                if (in_array($payment_type_record->payment_type, ['Bank', 'Check'])) {
                    $account_keyword = 'Bank';
                } else {
                    $account_keyword = 'Cash';
                }
            }
        }

        // Step 3: Get COA account ID dynamically (matches expense method)
        if (!empty($account_keyword)) {
            $payment_account_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id
                     FROM {$wpdb->prefix}orabooks_ac_coa_list
                     WHERE account_name = %s
                     AND status = 1
                     LIMIT 1",
                    $account_keyword
                )
            );
            
            if ($payment_account_id) {
                error_log("COA account found: $account_keyword with ID: $payment_account_id");
                return $payment_account_id;
            }
        }

        error_log("No COA account found for payment type: $payment_type_name");
        return null;
    }

    /**
     * Clean up existing journal entries for an asset
     */
    private function cleanup_asset_journal_entries($asset_id) {
        global $wpdb;
        
        $je_table = $wpdb->prefix . 'orabooks_ac_journal_entry';
        $je_line_table = $wpdb->prefix . 'orabooks_ac_journal_line';
        
        // Get journal entries for this asset
        $je_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM $je_table WHERE source_type = 'fixed_asset' AND source_id = %d",
            $asset_id
        ));
        
        if (!empty($je_ids)) {
            $je_ids_str = implode(',', array_map('intval', $je_ids));
            
            // Delete journal lines first (foreign key constraint)
            $wpdb->query("DELETE FROM $je_line_table WHERE journal_entry_id IN ($je_ids_str)");
            
            // Delete journal entries
            $wpdb->query("DELETE FROM $je_table WHERE id IN ($je_ids_str)");
            
            error_log("Cleaned up " . count($je_ids) . " journal entries for asset ID: $asset_id");
        }
    }

    public function ajax_sync_asset_journal() {
        check_ajax_referer('obn_assets_action_nonce', 'security');
        
        $asset_id = intval($_POST['asset_id'] ?? 0);
        if ($asset_id <= 0) {
            wp_send_json_error('Invalid asset ID.');
        }
        
        $result = $this->sync_journal_entry($asset_id);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Journal entry synced successfully.', 'journal_entry_id' => $result));
        } else {
            wp_send_json_error('Failed to sync journal entry.');
        }
    }

    public function get_asset() {
        check_ajax_referer('obn_assets_action_nonce', 'security');
        global $wpdb;
        $id = intval($_POST['id']);
        $asset = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_ac_assets WHERE id = %d", $id));
        
        if ($asset) {
            wp_send_json_success($asset);
        }
        wp_send_json_error('Asset not found.');
    }

    public function update_asset() {
        check_ajax_referer('obn_assets_action_nonce', 'security');
        
        global $wpdb;
        $id = intval($_POST['id']);
        $table = $wpdb->prefix . 'orabooks_ac_assets';

        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'category' => sanitize_text_field($_POST['category']),
            'purchase_date' => sanitize_text_field($_POST['purchase_date']),
            'cost' => floatval($_POST['cost']),
            'salvage_value' => floatval($_POST['salvage_value']),
            'useful_life_years' => intval($_POST['useful_life_years']),
            'asset_account_id' => intval($_POST['asset_account_id']),
            'depr_expense_account_id' => intval($_POST['depr_expense_account_id']),
            'accum_depr_account_id' => intval($_POST['accum_depr_account_id']),
            'payment_type_id' => !empty($_POST['payment_type_id']) ? intval($_POST['payment_type_id']) : null,
            'bank_account_id' => !empty($_POST['bank_account_id']) ? intval($_POST['bank_account_id']) : null,
        );

        $updated = $wpdb->update($table, $data, array('id' => $id));

        if ($updated !== false) {
            // Sync journal entry for updated asset
            $je_result = $this->sync_journal_entry($id);
            
            if ($je_result) {
                wp_send_json_success(array('message' => 'Asset updated successfully with journal entry.'));
            } else {
                error_log("Asset updated but journal entry sync failed for asset ID: $id");
                wp_send_json_success(array('message' => 'Asset updated successfully. Warning: Journal entry sync failed.'));
            }
        }
        wp_send_json_error('Failed to update asset.');
    }

    public function delete_asset() {
        check_ajax_referer('obn_assets_action_nonce', 'security');
        global $wpdb;
        $id = intval($_POST['id']);
        // Only allow delete if no depreciation records exist? Audit trail?
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}orabooks_ac_depreciation_records WHERE asset_id = %d", $id));
        if ($exists) {
            wp_send_json_error('Cannot delete asset with depreciation history. Dispose it instead.');
        }
        $wpdb->delete($wpdb->prefix . 'orabooks_ac_assets', array('id' => $id));
        wp_send_json_success(array('message' => 'Asset deleted.'));
    }

    public function insert_depr_method() {
        check_ajax_referer('obn_assets_action_nonce', 'security');
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_depreciation_methods';

        $name = sanitize_text_field($_POST['name']);
        $slug = sanitize_text_field($_POST['slug']);

        if (empty($name) || empty($slug)) {
            wp_send_json_error('Name and Slug are required.');
        }

        $data = array(
            'name' => $name,
            'slug' => $slug,
            'description' => sanitize_textarea_field($_POST['description']),
            'status' => 1
        );

        $inserted = $wpdb->insert($table, $data);
        if ($inserted) {
            wp_send_json_success(array('message' => 'Depreciation method added.'));
        }
        wp_send_json_error('Database error: Failed to insert method.');
    }

    // Asset Category Management Methods
    public function manage_asset_category() {
        check_ajax_referer('obn_assets_action_nonce', 'security');
        
        // Check user permissions - more flexible check for asset categories
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to manage asset categories.');
        }
        
        // Check if user has OBN permissions or is admin
        if (class_exists('OBN_Permissions')) {
            $has_permission = OBN_Permissions::has_view_permission('asset-category');
            $permitted_ids = OBN_Permissions::get_user_permitted_ids(get_current_user_id());
            $has_admin_access = ($permitted_ids === true || in_array('asset-category', $permitted_ids));
        } else {
            $has_admin_access = current_user_can('manage_options');
        }
        
        if (!$has_admin_access && !$has_permission) {
            wp_send_json_error('You do not have permission to manage asset categories.');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_asset_category';
        
        $category_id = intval($_POST['category_id'] ?? 0);
        $category_name = sanitize_text_field($_POST['category_name'] ?? '');
        $category_code = sanitize_text_field($_POST['category_code'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        
        if (empty($category_name)) {
            wp_send_json_error('Category name is required.');
        }
        
        $data = array(
            'category_name' => $category_name,
            'description' => $description,
            'created_by' => get_current_user_id(),
            'created_date' => current_time('Y-m-d'),
            'created_time' => current_time('H:i:s')
        );
        
        if (!empty($category_code)) {
            $data['category_code'] = $category_code;
        }
        
        if ($category_id > 0) {
            // Update existing category
            $result = $wpdb->update($table, $data, array('id' => $category_id));
            if ($result !== false) {
                wp_send_json_success(array('message' => 'Asset category updated successfully.'));
            } else {
                wp_send_json_error('Database error: Failed to update category.');
            }
        } else {
            // Insert new category
            $data['store_id'] = get_current_blog_id();
            $data['created_by'] = get_current_user_id();
            $data['created_date'] = current_time('Y-m-d');
            $data['created_time'] = current_time('H:i:s');
            $data['system_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
            $data['system_name'] = gethostname();
            $data['status'] = 1;
            
            $result = $wpdb->insert($table, $data);
            if ($result) {
                wp_send_json_success(array('message' => 'Asset category created successfully.'));
            } else {
                wp_send_json_error('Database error: Failed to create category.');
            }
        }
    }
    
    public function get_asset_category() {
        check_ajax_referer('obn_assets_action_nonce', 'security');
        
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_asset_category';
        
        $category_id = intval($_POST['id'] ?? 0);
        if ($category_id <= 0) {
            wp_send_json_error('Invalid category ID.');
        }
        
        $category = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $category_id
        ));
        
        if ($category) {
            wp_send_json_success($category);
        } else {
            wp_send_json_error('Category not found.');
        }
    }
    
    public function toggle_asset_category_status() {
        check_ajax_referer('obn_assets_action_nonce', 'security');
        
        // Check user permissions - more flexible check for asset categories
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to manage asset categories.');
        }
        
        // Check if user has OBN permissions or is admin
        if (class_exists('OBN_Permissions')) {
            $has_permission = OBN_Permissions::has_view_permission('asset-category');
            $permitted_ids = OBN_Permissions::get_user_permitted_ids(get_current_user_id());
            $has_admin_access = ($permitted_ids === true || in_array('asset-category', $permitted_ids));
        } else {
            $has_admin_access = current_user_can('manage_options');
        }
        
        if (!$has_admin_access && !$has_permission) {
            wp_send_json_error('You do not have permission to manage asset categories.');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_asset_category';
        
        $category_id = intval($_POST['id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);
        
        if ($category_id <= 0) {
            wp_send_json_error('Invalid category ID.');
        }
        
        $result = $wpdb->update($table, 
            array('status' => $status), 
            array('id' => $category_id)
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'Category status updated.'));
        } else {
            wp_send_json_error('Database error: Failed to update status.');
        }
    }
    
    public function delete_asset_category() {
        check_ajax_referer('obn_assets_action_nonce', 'security');
        
        // Check user permissions - more flexible check for asset categories
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to manage asset categories.');
        }
        
        // Check if user has OBN permissions or is admin
        if (class_exists('OBN_Permissions')) {
            $has_permission = OBN_Permissions::has_view_permission('asset-category');
            $permitted_ids = OBN_Permissions::get_user_permitted_ids(get_current_user_id());
            $has_admin_access = ($permitted_ids === true || in_array('asset-category', $permitted_ids));
        } else {
            $has_admin_access = current_user_can('manage_options');
        }
        
        if (!$has_admin_access && !$has_permission) {
            wp_send_json_error('You do not have permission to manage asset categories.');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_asset_category';
        $asset_table = $wpdb->prefix . 'orabooks_ac_assets';
        
        $category_id = intval($_POST['id'] ?? 0);
        if ($category_id <= 0) {
            wp_send_json_error('Invalid category ID.');
        }
        
        // Check if category is being used by any assets
        $assets_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $asset_table WHERE category = %d",
            $category_id
        ));
        
        if ($assets_count > 0) {
            wp_send_json_error('Cannot delete category. It is being used by ' . $assets_count . ' asset(s).');
        }
        
        $result = $wpdb->delete($table, array('id' => $category_id));
        
        if ($result) {
            wp_send_json_success(array('message' => 'Asset category deleted successfully.'));
        } else {
            wp_send_json_error('Database error: Failed to delete category.');
        }
    }
}

new OBN_Assets();
