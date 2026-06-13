<?php
/**
 * Handles Opening Balance Input logic
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class OBN_Opening_Balances {

    public function __construct() {
        add_action('wp_ajax_obn_save_opening_balances', array( $this, 'save_opening_balances' ) );
        add_action('wp_ajax_nopriv_obn_save_opening_balances', array( $this, 'save_opening_balances' ) );
        
        add_action('wp_ajax_obn_get_coa_for_opening', array( $this, 'get_coa_for_opening' ) );
        add_action('wp_ajax_nopriv_obn_get_coa_for_opening', array( $this, 'get_coa_for_opening' ) );
    }

    public function get_coa_for_opening() {
        check_ajax_referer('obn_opening_balance_nonce', 'security');
        
        global $wpdb;
        $coa_table = $wpdb->prefix . 'orabooks_ac_coa_list';
        $type_table = $wpdb->prefix . 'orabooks_ac_coa_types';
        
        $results = $wpdb->get_results( "
            SELECT c.*, t.coa_type 
            FROM $coa_table c 
            LEFT JOIN $type_table t ON c.coa_type_id = t.id 
            WHERE c.status = 1 
            ORDER BY t.coa_type, c.account_name ASC" );
            
        wp_send_json_success($results);
    }

    public function save_opening_balances() {
        check_ajax_referer('obn_opening_balance_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        try {
            $store_id = 1; // Default for now
            $entry_date = sanitize_text_field($_POST['entry_date'] ?? date('Y-m-d'));
            
            // Delete existing draft opening balances for this store/date? 
            // Better to overwrite or clear old ones if not locked.
            
            $ob_table = $wpdb->prefix . 'orabooks_ac_opening_balances';
            $wpdb->delete($ob_table, array('store_id' => $store_id, 'status' => 'Draft'));

            // 1. CoA Balances
            $coa_balances = $_POST['coa_balances'] ?? [];
            foreach ($coa_balances as $acc_id => $vals) {
                $debit = floatval($vals['debit'] ?? 0);
                $credit = floatval($vals['credit'] ?? 0);
                if ($debit > 0 || $credit > 0) {
                    $wpdb->insert($ob_table, array(
                        'store_id' => $store_id,
                        'account_id' => $acc_id,
                        'account_type' => 'COA',
                        'debit' => $debit,
                        'credit' => $credit,
                        'entry_date' => $entry_date,
                        'status' => 'Draft'
                    ));
                }
            }

            // 2. Customer Balances
            $cust_balances = $_POST['customer_balances'] ?? [];
            foreach ($cust_balances as $cust_id => $vals) {
                $debit = floatval($vals['debit'] ?? 0);
                $credit = floatval($vals['credit'] ?? 0);
                if ($debit > 0 || $credit > 0) {
                    $wpdb->insert($ob_table, array(
                        'store_id' => $store_id,
                        'party_id' => $cust_id,
                        'party_type' => 'Customer',
                        'account_type' => 'AR',
                        'debit' => $debit,
                        'credit' => $credit,
                        'entry_date' => $entry_date,
                        'status' => 'Draft'
                    ));
                }
            }

            // 3. Supplier Balances
            $supp_balances = $_POST['supplier_balances'] ?? [];
            foreach ($supp_balances as $supp_id => $vals) {
                $debit = floatval($vals['debit'] ?? 0);
                $credit = floatval($vals['credit'] ?? 0);
                if ($debit > 0 || $credit > 0) {
                    $wpdb->insert($ob_table, array(
                        'store_id' => $store_id,
                        'party_id' => $supp_id,
                        'party_type' => 'Supplier',
                        'account_type' => 'AP',
                        'debit' => $debit,
                        'credit' => $credit,
                        'entry_date' => $entry_date,
                        'status' => 'Draft'
                    ));
                }
            }

            // 4. Inventory Opening
            $inv_table = $wpdb->prefix . 'orabooks_ac_inventory_opening';
            $wpdb->delete($inv_table, array('store_id' => $store_id));
            
            $inventory_items = $_POST['inventory_items'] ?? [];
            foreach ($inventory_items as $item_id => $vals) {
                $qty = floatval($vals['qty'] ?? 0);
                $cost = floatval($vals['cost'] ?? 0);
                if ($qty > 0) {
                    $wpdb->insert($inv_table, array(
                        'store_id' => $store_id,
                        'item_id' => $item_id,
                        'quantity' => $qty,
                        'unit_cost' => $cost,
                        'total_cost' => $qty * $cost,
                        'entry_date' => $entry_date
                    ));
                }
            }

            // Validate Equality
            $total_debit = floatval($wpdb->get_var($wpdb->prepare("SELECT SUM(debit) FROM $ob_table WHERE store_id = %d AND status = 'Draft'", $store_id)));
            $total_credit = floatval($wpdb->get_var($wpdb->prepare("SELECT SUM(credit) FROM $ob_table WHERE store_id = %d AND status = 'Draft'", $store_id)));
            
            // Add Inventory to Debits (Assets)
            $total_inv = floatval($wpdb->get_var($wpdb->prepare("SELECT SUM(total_cost) FROM $inv_table WHERE store_id = %d", $store_id)));
            $total_debit += $total_inv;

            if (abs($total_debit - $total_credit) > 0.001) {
                if (isset($_POST['auto_adjust']) && $_POST['auto_adjust'] == '1') {
                    // Adjust to "Opening Balance Equity"
                    $diff = $total_debit - $total_credit;
                    $obe_account_id = $this->ensure_obe_account();
                    
                    $adj_debit = ($diff < 0) ? abs($diff) : 0;
                    $adj_credit = ($diff > 0) ? $diff : 0;
                    
                    $existing_obe = $wpdb->get_row($wpdb->prepare("SELECT * FROM $ob_table WHERE store_id = %d AND account_id = %d AND status = 'Draft'", $store_id, $obe_account_id));
                    
                    if ($existing_obe) {
                        $net = ($existing_obe->debit + $adj_debit) - ($existing_obe->credit + $adj_credit);
                        $new_debit = ($net > 0) ? $net : 0;
                        $new_credit = ($net < 0) ? abs($net) : 0;
                        
                        $wpdb->update($ob_table, array(
                            'debit' => $new_debit,
                            'credit' => $new_credit,
                            'description' => 'User entry + Auto-adjustment for opening balance'
                        ), array('id' => $existing_obe->id));
                    } else {
                        $wpdb->insert($ob_table, array(
                            'store_id' => $store_id,
                            'account_id' => $obe_account_id,
                            'account_type' => 'COA',
                            'debit' => $adj_debit,
                            'credit' => $adj_credit,
                            'entry_date' => $entry_date,
                            'description' => 'Auto-adjustment for opening balance',
                            'status' => 'Draft'
                        ));
                    }
                } else {
                    $wpdb->query('ROLLBACK');
                    wp_send_json_error(array('message' => 'Total Debits must equal Total Credits. Difference: ' . ($total_debit - $total_credit)));
                    return;
                }
            }

            // If locked, convert to journal entry
            if (isset($_POST['lock']) && $_POST['lock'] == '1') {
                $this->convert_to_journal_entry($store_id, $entry_date);
                $wpdb->update($ob_table, array('status' => 'Locked'), array('store_id' => $store_id, 'status' => 'Draft'));
            }

            $wpdb->query('COMMIT');
            wp_send_json_success(array('message' => 'Opening balances saved successfully.'));
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    private function ensure_obe_account() {
        global $wpdb;
        $coa_table = $wpdb->prefix . 'orabooks_ac_coa_list';
        $type_table = $wpdb->prefix . 'orabooks_ac_coa_types';
        
        $obe_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $coa_table WHERE account_name = %s", 'Opening Balance Equity'));
        
        if (!$obe_id) {
            $equity_type_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $type_table WHERE coa_type = %s", 'Equity'));
            if (!$equity_type_id) {
                $wpdb->insert($type_table, array('coa_type' => 'Equity', 'status' => 1));
                $equity_type_id = $wpdb->insert_id;
            }
            
            $wpdb->insert($coa_table, array(
                'coa_type_id' => $equity_type_id,
                'account_code' => '3000',
                'account_name' => 'Opening Balance Equity',
                'description' => 'System account for opening balance offsets',
                'status' => 1
            ));
            $obe_id = $wpdb->insert_id;
        }
        
        return $obe_id;
    }

    private function convert_to_journal_entry($store_id, $entry_date) {
        global $wpdb;
        $ob_table = $wpdb->prefix . 'orabooks_ac_opening_balances';
        $inv_table = $wpdb->prefix . 'orabooks_ac_inventory_opening';
        $je_table = $wpdb->prefix . 'orabooks_ac_journal_entry';
        $jl_table = $wpdb->prefix . 'orabooks_ac_journal_line';
        
        // Fetch all data
        $balances = $wpdb->get_results($wpdb->prepare("SELECT * FROM $ob_table WHERE store_id = %d AND status = 'Draft'", $store_id));
        $inventory = $wpdb->get_results($wpdb->prepare("SELECT * FROM $inv_table WHERE store_id = %d", $store_id));
        
        $total_debit = 0;
        $total_credit = 0;
        foreach($balances as $b) {
            $total_debit += $b->debit;
            $total_credit += $b->credit;
        }
        $inv_total = 0;
        foreach($inventory as $i) {
            $inv_total += $i->total_cost;
        }
        $total_debit += $inv_total;

        // Create Header
        $wpdb->insert($je_table, array(
            'store_id' => $store_id,
            'entry_date' => $entry_date,
            'posting_date' => $entry_date,
            'entry_number' => 'OB-' . date('Ymd'),
            'source_type' => 'OpeningBalance',
            'description' => 'Initial opening balances',
            'total_debit' => $total_debit,
            'total_credit' => $total_credit,
            'status' => 'Posted'
        ));
        $je_id = $wpdb->insert_id;

        // Add lines
        foreach($balances as $b) {
            $acc_id = $b->account_id;
            if (!$acc_id) {
                // If it's AR or AP, we might need to map to a general AR/AP account
                // For now, let's assume we find it by type
                if ($b->account_type == 'AR') {
                    $acc_id = $this->get_special_account('Accounts Receivable');
                } else if ($b->account_type == 'AP') {
                    $acc_id = $this->get_special_account('Accounts Payable');
                }
            }
            
            if ($acc_id) {
                $wpdb->insert($jl_table, array(
                    'journal_entry_id' => $je_id,
                    'account_id' => $acc_id,
                    'contact_id' => $b->party_id,
                    'debit' => $b->debit,
                    'credit' => $b->credit,
                    'debit_amt' => $b->debit,
                    'credit_amt' => $b->credit,
                    'description' => $b->description ?: 'Opening balance'
                ));
            }
        }

        // Add Inventory line
        if ($inv_total > 0) {
            $inv_acc_id = $this->get_special_account('Inventory');
            $wpdb->insert($jl_table, array(
                'journal_entry_id' => $je_id,
                'account_id' => $inv_acc_id,
                'debit' => $inv_total,
                'credit' => 0,
                'debit_amt' => $inv_total,
                'credit_amt' => 0,
                'description' => 'Opening inventory'
            ));
        }
    }

    private function get_special_account($name) {
        global $wpdb;
        $coa_table = $wpdb->prefix . 'orabooks_ac_coa_list';
        return $wpdb->get_var($wpdb->prepare("SELECT id FROM $coa_table WHERE account_name = %s LIMIT 1", $name));
    }
}

new OBN_Opening_Balances();
