<?php
/**
 * Frontend-Accounting Reports handlers
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class OBN_Reports {

    public function __construct() {
        add_action('wp_ajax_obn_filter_cash_transactions', array($this, 'filter_cash_transactions'));
        add_action('wp_ajax_nopriv_obn_filter_cash_transactions', array($this, 'filter_cash_transactions'));

        add_action('wp_ajax_obn_delete_cash_transaction', array($this, 'delete_cash_transaction'));
        add_action('wp_ajax_nopriv_obn_delete_cash_transaction', array($this, 'delete_cash_transaction'));

        add_action('wp_ajax_search_journal_report', array($this, 'handle_search_journal_report'));
        add_action('wp_ajax_nopriv_search_journal_report', array($this, 'handle_search_journal_report'));

        add_action('wp_ajax_search_trial_balance_report', array($this, 'handle_search_trial_balance_report'));
        add_action('wp_ajax_nopriv_search_trial_balance_report', array($this, 'handle_search_trial_balance_report'));

        add_action('wp_ajax_search_income_statement_report', array($this, 'handle_search_income_statement_report'));
        add_action('wp_ajax_nopriv_search_income_statement_report', array($this, 'handle_search_income_statement_report'));

        add_action('wp_ajax_search_balance_sheet_report', array($this, 'handle_search_balance_sheet_report'));
        add_action('wp_ajax_nopriv_search_balance_sheet_report', array($this, 'handle_search_balance_sheet_report'));

        add_action('wp_ajax_search_ledger_report', array($this, 'handle_search_ledger_report'));
        add_action('wp_ajax_nopriv_search_ledger_report', array($this, 'handle_search_ledger_report'));
    }

    public function filter_cash_transactions() {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        ob_start();
        include OBN_ACCOUNTING_PLUGIN_DIR . 'includes/reports/cash-transactions.php';
        $content = ob_get_clean();

        wp_send_json_success($content);
    }

    public function delete_cash_transaction() {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $type = sanitize_text_field($_POST['type'] ?? '');

        if ($id <= 0 || !in_array($type, ['Sales', 'Purchase'])) {
            wp_send_json_error('Invalid parameters.');
        }

        $table = ($type === 'Sales') ? $wpdb->prefix . 'orabooks_db_salespayments' : $wpdb->prefix . 'orabooks_db_purchasepayments';

        $deleted = $wpdb->delete($table, ['id' => $id], ['%d']);

        if ($deleted) {
            wp_send_json_success('Transaction deleted successfully.');
        } else {
            wp_send_json_error('Failed to delete transaction.');
        }
    }

    public function handle_search_journal_report() {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            echo '<tr><td colspan="8" class="px-6 py-10 text-center text-red-500">Access denied.</td></tr>';
            wp_die();
        }

        global $wpdb;

        $start       = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end         = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $source_type = isset($_POST['source_type']) ? sanitize_text_field($_POST['source_type']) : '';
        $user_id     = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        // Check if tables exist
        $je_table   = "{$wpdb->prefix}orabooks_ac_journal_entry";
        $jl_table   = "{$wpdb->prefix}orabooks_ac_journal_line";
        $coa_table  = "{$wpdb->prefix}orabooks_ac_coa_list";

        if ($wpdb->get_var("SHOW TABLES LIKE '$je_table'") != $je_table) {
            echo '<tr><td colspan="8" class="px-6 py-10 text-center text-red-500">Journal Entry table missing.</td></tr>';
            wp_die();
        }

        $where = "WHERE 1=1";
        if (!empty($start))       $where .= $wpdb->prepare(" AND je.entry_date >= %s", $start);
        if (!empty($end))         $where .= $wpdb->prepare(" AND je.entry_date <= %s", $end);
        if (!empty($source_type)) $where .= $wpdb->prepare(" AND je.source_type = %s", $source_type);
        if ($user_id > 0)         $where .= $wpdb->prepare(" AND je.created_by = %d", $user_id);

        $sql = "SELECT je.entry_number, je.entry_date, je.reference_no, je.description as je_desc, je.id as je_id,
                       jl.description as line_desc, jl.debit, jl.credit, jl.line_number,
                       coal.account_name, coal.account_code
                FROM $je_table je
                INNER JOIN $jl_table jl ON je.id = jl.journal_entry_id
                LEFT JOIN $coa_table coal ON jl.account_id = coal.id
                $where
                ORDER BY je.entry_date DESC, je.id DESC, jl.line_number ASC
                LIMIT 5000";
        
        $rows = $wpdb->get_results($sql);

        if ($wpdb->last_error) {
            echo '<tr><td colspan="8" class="px-6 py-10 text-center text-red-500">Database Error: ' . esc_html($wpdb->last_error) . '</td></tr>';
            wp_die();
        }

        if ($rows) {
            $prev_je_id = null;
            $row_idx = 1;
            foreach ($rows as $r) {
                $is_new_je = ($prev_je_id !== $r->je_id);
                $date_disp = $is_new_je ? date('d-m-Y', strtotime($r->entry_date)) : '';
                $num_disp  = $is_new_je ? esc_html($r->entry_number) : '';
                $ref_disp  = $is_new_je ? esc_html($r->reference_no) : '';
                
                $acc_info  = esc_html(($r->account_name ?: 'Unknown') . ' (' . ($r->account_code ?: 'N/A') . ')');
                $desc_disp = esc_html($r->line_desc ?: $r->je_desc);
                $debit_val = ($r->debit > 0) ? number_format($r->debit, 2) : '-';
                $credit_val = ($r->credit > 0) ? number_format($r->credit, 2) : '-';

                $border_class = $is_new_je ? 'border-t-2 border-gray-200' : 'border-gray-100';
                
                echo "<tr class='hover:bg-gray-50 transition-colors border-b $border_class'>";
                echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-500'>" . ($is_new_je ? $row_idx : '') . "</td>";
                echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-500'>$date_disp</td>";
                echo "<td class='px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600'>$num_disp</td>";
                echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-500'>$ref_disp</td>";
                echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900'>$acc_info</td>";
                echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-600'>$desc_disp</td>";
                echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900 debit-amt'>$debit_val</td>";
                echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900 credit-amt'>$credit_val</td>";
                echo "</tr>";
                
                if ($is_new_je) {
                    $row_idx++;
                    $prev_je_id = $r->je_id;
                }
            }
        } else {
            echo '<tr><td colspan="8" class="px-6 py-10 text-center text-gray-500">No matching journal records found.</td></tr>';
        }
        wp_die();
    }

    public function handle_search_trial_balance_report() {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            echo '<tr><td colspan="5" class="px-6 py-10 text-center text-red-500">Access denied.</td></tr>';
            wp_die();
        }

        global $wpdb;

        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-01');
        $end_date   = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : date('Y-m-d');

        // Check if tables exist
        $je_table   = "{$wpdb->prefix}orabooks_ac_journal_entry";
        $jl_table   = "{$wpdb->prefix}orabooks_ac_journal_line";
        $coa_table  = "{$wpdb->prefix}orabooks_ac_coa_list";

        if ($wpdb->get_var("SHOW TABLES LIKE '$coa_table'") != $coa_table) {
            echo '<tr><td colspan="5" class="px-6 py-10 text-center text-red-500">Accounting tables missing.</td></tr>';
            wp_die();
        }

        // We fetch all accounts and their summed debits/credits within the date range
        $sql = $wpdb->prepare("
            SELECT 
                coal.account_name, 
                coal.account_code,
                COALESCE(SUM(jl.debit), 0) as total_debit,
                COALESCE(SUM(jl.credit), 0) as total_credit
            FROM $coa_table coal
            LEFT JOIN $jl_table jl ON coal.id = jl.account_id
            LEFT JOIN $je_table je ON jl.journal_entry_id = je.id AND je.status = 'Posted' AND je.entry_date >= %s AND je.entry_date <= %s
            GROUP BY coal.id
            HAVING (total_debit != 0 OR total_credit != 0)
            ORDER BY coal.account_code ASC
        ", $start_date, $end_date);
        
        $rows = $wpdb->get_results($sql);

        if ($rows) {
            $i = 1;
            foreach ($rows as $r) {
                $debit = floatval($r->total_debit);
                $credit = floatval($r->total_credit);
                
                echo '<tr class="hover:bg-gray-50 transition-colors border-b border-gray-100">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . $i . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">' . esc_html($r->account_code) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">' . esc_html($r->account_name) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900 debit-amt">' . ($debit > 0 ? number_format($debit, 2) : '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900 credit-amt">' . ($credit > 0 ? number_format($credit, 2) : '-') . '</td>
                </tr>';
                $i++;
            }
        } else {
            echo '<tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">No records found for the selected period.</td></tr>';
        }
        wp_die();
    }

    public function handle_search_income_statement_report() {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            echo '<tr><td colspan="3" class="px-6 py-10 text-center text-red-500">Access denied.</td></tr>';
            wp_die();
        }

        global $wpdb;

        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-01');
        $end_date   = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : date('Y-m-d');

        $je_table   = "{$wpdb->prefix}orabooks_ac_journal_entry";
        $jl_table   = "{$wpdb->prefix}orabooks_ac_journal_line";
        $coa_table  = "{$wpdb->prefix}orabooks_ac_coa_list";
        $types_table = "{$wpdb->prefix}orabooks_ac_coa_types";

        if ($wpdb->get_var("SHOW TABLES LIKE '$coa_table'") != $coa_table) {
            echo '<tr><td colspan="3" class="px-6 py-10 text-center text-red-500">Accounting tables missing.</td></tr>';
            wp_die();
        }

        $sql = $wpdb->prepare("
            SELECT 
                coal.account_name, 
                coal.account_code,
                coal.coa_type_id,
                t.coa_type as type_name,
                SUM(jl.debit) as total_debit,
                SUM(jl.credit) as total_credit
            FROM $coa_table coal
            LEFT JOIN $jl_table jl ON coal.id = jl.account_id
            LEFT JOIN $je_table je ON jl.journal_entry_id = je.id AND je.status = 'Posted' AND je.entry_date >= %s AND je.entry_date <= %s
            JOIN $types_table t ON coal.coa_type_id = t.id
            WHERE coal.coa_type_id IN (4, 5, 6)
            GROUP BY coal.id
            HAVING (SUM(jl.debit) != 0 OR SUM(jl.credit) != 0)
            ORDER BY coal.coa_type_id ASC, coal.account_code ASC
        ", $start_date, $end_date);
        
        $rows = $wpdb->get_results($sql);

        $categories = [
            4 => ['name' => 'Revenue / Income', 'data' => [], 'total' => 0],
            5 => ['name' => 'Cost of Goods Sold', 'data' => [], 'total' => 0],
            6 => ['name' => 'Expenses', 'data' => [], 'total' => 0]
        ];

        if ($rows) {
            foreach ($rows as $r) {
                $type_id = intval($r->coa_type_id);
                $debit = floatval($r->total_debit);
                $credit = floatval($r->total_credit);
                
                if ($type_id == 4) {
                    $balance = $credit - $debit;
                } else {
                    $balance = $debit - $credit;
                }

                if ($balance != 0) {
                    $categories[$type_id]['data'][] = [
                        'name' => $r->account_name,
                        'code' => $r->account_code,
                        'amount' => $balance
                    ];
                    $categories[$type_id]['total'] += $balance;
                }
            }
        }

        $html = '';
        $html .= '<tr class="bg-gray-50 font-bold"><td colspan="2" class="px-6 py-3 text-sm text-gray-900 uppercase">Revenue</td><td class="px-6 py-3"></td></tr>';
        if (!empty($categories[4]['data'])) {
            foreach ($categories[4]['data'] as $item) {
                $html .= '<tr class="border-b border-gray-100"><td class="px-6 py-3 text-sm text-gray-600 pl-10">' . esc_html($item['name']) . '</td><td class="px-6 py-3 text-sm text-right">' . number_format($item['amount'], 2) . '</td><td class="px-6 py-3"></td></tr>';
            }
        } else {
            $html .= '<tr><td class="px-6 py-3 text-sm text-gray-400 pl-10 italic">No revenue recorded</td><td class="px-6 py-3 text-sm text-right">0.00</td><td class="px-6 py-3"></td></tr>';
        }
        $html .= '<tr class="font-bold border-b-2 border-gray-200"><td class="px-6 py-3 text-sm text-gray-900 pl-6 uppercase">Total Revenue</td><td class="px-6 py-3"></td><td class="px-6 py-3 text-sm text-right">' . number_format($categories[4]['total'], 2) . '</td></tr>';

        $html .= '<tr class="bg-gray-50 font-bold mt-4"><td colspan="2" class="px-6 py-3 text-sm text-gray-900 uppercase">Cost of Goods Sold</td><td class="px-6 py-3"></td></tr>';
        if (!empty($categories[5]['data'])) {
            foreach ($categories[5]['data'] as $item) {
                $html .= '<tr class="border-b border-gray-100"><td class="px-6 py-3 text-sm text-gray-600 pl-10">' . esc_html($item['name']) . '</td><td class="px-6 py-3 text-sm text-right">' . number_format($item['amount'], 2) . '</td><td class="px-6 py-3"></td></tr>';
            }
        } else {
            $html .= '<tr><td class="px-6 py-3 text-sm text-gray-400 pl-10 italic">No COGS recorded</td><td class="px-6 py-3 text-sm text-right">0.00</td><td class="px-6 py-3"></td></tr>';
        }
        $html .= '<tr class="font-bold border-b-2 border-gray-200"><td class="px-6 py-3 text-sm text-gray-900 pl-6 uppercase">Total COGS</td><td class="px-6 py-3"></td><td class="px-6 py-3 text-sm text-right text-red-600">' . number_format($categories[5]['total'], 2) . '</td></tr>';

        $gross_profit = $categories[4]['total'] - $categories[5]['total'];
        $html .= '<tr class="bg-indigo-50 font-extrabold text-indigo-800"><td class="px-6 py-4 text-md uppercase">Gross Profit</td><td class="px-6 py-4"></td><td class="px-6 py-4 text-right text-md">' . number_format($gross_profit, 2) . '</td></tr>';

        $html .= '<tr class="bg-gray-50 font-bold mt-4"><td colspan="2" class="px-6 py-3 text-sm text-gray-900 uppercase">Operating Expenses</td><td class="px-6 py-3"></td></tr>';
        if (!empty($categories[6]['data'])) {
            foreach ($categories[6]['data'] as $item) {
                $html .= '<tr class="border-b border-gray-100"><td class="px-6 py-3 text-sm text-gray-600 pl-10">' . esc_html($item['name']) . '</td><td class="px-6 py-3 text-sm text-right">' . number_format($item['amount'], 2) . '</td><td class="px-6 py-3"></td></tr>';
            }
        } else {
            $html .= '<tr><td class="px-6 py-3 text-sm text-gray-400 pl-10 italic">No expenses recorded</td><td class="px-6 py-3 text-sm text-right">0.00</td><td class="px-6 py-3"></td></tr>';
        }
        $html .= '<tr class="font-bold border-b-2 border-gray-200"><td class="px-6 py-3 text-sm text-gray-900 pl-6 uppercase">Total Operating Expenses</td><td class="px-6 py-3"></td><td class="px-6 py-3 text-sm text-right text-red-600">' . number_format($categories[6]['total'], 2) . '</td></tr>';

        $net_profit = $gross_profit - $categories[6]['total'];
        $html .= '<tr class="bg-indigo-600 text-white font-extrabold"><td class="px-6 py-4 text-md uppercase">Net Profit / Loss</td><td class="px-6 py-4"></td><td class="px-6 py-4 text-right text-md">' . number_format($net_profit, 2) . '</td></tr>';

        echo $html;
        wp_die();
    }

    public function handle_search_balance_sheet_report() {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            echo '<tr><td colspan="3" class="px-6 py-10 text-center text-red-500">Access denied.</td></tr>';
            wp_die();
        }

        global $wpdb;

        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-01');
        $end_date   = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : date('Y-m-d');

        $je_table    = "{$wpdb->prefix}orabooks_ac_journal_entry";
        $jl_table    = "{$wpdb->prefix}orabooks_ac_journal_line";
        $coa_table   = "{$wpdb->prefix}orabooks_ac_coa_list";
        $types_table = "{$wpdb->prefix}orabooks_ac_coa_types";

        if ($wpdb->get_var("SHOW TABLES LIKE '$coa_table'") != $coa_table) {
            echo '<tr><td colspan="3" class="px-6 py-10 text-center text-red-500">Accounting tables missing.</td></tr>';
            wp_die();
        }

        $ni_sql = $wpdb->prepare("
            SELECT 
                coal.coa_type_id,
                SUM(jl.debit) as total_debit,
                SUM(jl.credit) as total_credit
            FROM $coa_table coal
            LEFT JOIN $jl_table jl ON coal.id = jl.account_id
            LEFT JOIN $je_table je ON jl.journal_entry_id = je.id AND je.status = 'Posted' AND je.entry_date >= %s AND je.entry_date <= %s
            WHERE coal.coa_type_id IN (4, 5, 6)
            GROUP BY coal.coa_type_id
        ", $start_date, $end_date);
        
        $ni_rows = $wpdb->get_results($ni_sql);
        $net_income = 0;
        foreach ($ni_rows as $nir) {
            $type = intval($nir->coa_type_id);
            $d = floatval($nir->total_debit);
            $c = floatval($nir->total_credit);
            if ($type == 4) { $net_income += ($c - $d); }
            else { $net_income -= ($d - $c); }
        }

        $sql = $wpdb->prepare("
            SELECT 
                coal.account_name, 
                coal.account_code,
                coal.coa_type_id,
                t.coa_type as type_name,
                SUM(jl.debit) as total_debit,
                SUM(jl.credit) as total_credit
            FROM $coa_table coal
            LEFT JOIN $jl_table jl ON coal.id = jl.account_id
            LEFT JOIN $je_table je ON jl.journal_entry_id = je.id AND je.status = 'Posted' AND je.entry_date >= %s AND je.entry_date <= %s
            JOIN $types_table t ON coal.coa_type_id = t.id
            WHERE coal.coa_type_id IN (1, 2, 3)
            GROUP BY coal.id
            HAVING (SUM(jl.debit) != 0 OR SUM(jl.credit) != 0)
            ORDER BY coal.coa_type_id ASC, coal.account_code ASC
        ", $start_date, $end_date);
        
        $rows = $wpdb->get_results($sql);

        $categories = [
            1 => ['name' => 'Assets', 'data' => [], 'total' => 0],
            2 => ['name' => 'Liabilities', 'data' => [], 'total' => 0],
            3 => ['name' => 'Equity', 'data' => [], 'total' => 0]
        ];

        if ($rows) {
            foreach ($rows as $r) {
                $type_id = intval($r->coa_type_id);
                $debit = floatval($r->total_debit);
                $credit = floatval($r->total_credit);
                
                if ($type_id == 1) {
                    $balance = $debit - $credit;
                } else {
                    $balance = $credit - $debit;
                }

                if ($balance != 0) {
                    $categories[$type_id]['data'][] = [
                        'name' => $r->account_name,
                        'code' => $r->account_code,
                        'amount' => $balance
                    ];
                    $categories[$type_id]['total'] += $balance;
                }
            }
        }

        $html = '';
        $html .= '<tr class="bg-gray-50 font-bold"><td colspan="2" class="px-6 py-3 text-sm text-gray-900 uppercase">Assets</td><td class="px-6 py-3"></td></tr>';
        if (!empty($categories[1]['data'])) {
            foreach ($categories[1]['data'] as $item) {
                $html .= '<tr class="border-b border-gray-100"><td class="px-6 py-3 text-sm text-gray-600 pl-10">' . esc_html($item['name']) . '</td><td class="px-6 py-3 text-sm text-right">' . number_format($item['amount'], 2) . '</td><td class="px-6 py-3"></td></tr>';
            }
        } else {
            $html .= '<tr><td class="px-6 py-3 text-sm text-gray-400 pl-10 italic">No assets found</td><td class="px-6 py-3 text-sm text-right">0.00</td><td class="px-6 py-3"></td></tr>';
        }
        $html .= '<tr class="bg-indigo-50 font-extrabold text-indigo-700 border-b-2 border-indigo-200"><td class="px-6 py-4 text-md uppercase pl-6">Total Assets</td><td class="px-6 py-4"></td><td class="px-6 py-4 text-right text-md">' . number_format($categories[1]['total'], 2) . '</td></tr>';

        $html .= '<tr class="bg-gray-50 font-bold mt-4"><td colspan="2" class="px-6 py-3 text-sm text-gray-900 uppercase">Liabilities</td><td class="px-6 py-3"></td></tr>';
        if (!empty($categories[2]['data'])) {
            foreach ($categories[2]['data'] as $item) {
                $html .= '<tr class="border-b border-gray-100"><td class="px-6 py-3 text-sm text-gray-600 pl-10">' . esc_html($item['name']) . '</td><td class="px-6 py-3 text-sm text-right">' . number_format($item['amount'], 2) . '</td><td class="px-6 py-3"></td></tr>';
            }
        } else {
            $html .= '<tr><td class="px-6 py-3 text-sm text-gray-400 pl-10 italic">No liabilities found</td><td class="px-6 py-3 text-sm text-right">0.00</td><td class="px-6 py-3"></td></tr>';
        }
        $html .= '<tr class="font-bold border-b border-gray-200"><td class="px-6 py-3 text-sm text-gray-900 pl-6 uppercase text-indigo-700">Total Liabilities</td><td class="px-6 py-3 text-sm text-right text-indigo-600">' . number_format($categories[2]['total'], 2) . '</td><td class="px-6 py-3"></td></tr>';

        $html .= '<tr class="bg-gray-50 font-bold mt-4"><td colspan="2" class="px-6 py-3 text-sm text-gray-900 uppercase">Equity</td><td class="px-6 py-3"></td></tr>';
        if (!empty($categories[3]['data'])) {
            foreach ($categories[3]['data'] as $item) {
                $html .= '<tr class="border-b border-gray-100"><td class="px-6 py-3 text-sm text-gray-600 pl-10">' . esc_html($item['name']) . '</td><td class="px-6 py-3 text-sm text-right">' . number_format($item['amount'], 2) . '</td><td class="px-6 py-3"></td></tr>';
            }
        }
        
        $html .= '<tr class="border-b border-gray-100 italic"><td class="px-6 py-3 text-sm text-gray-600 pl-10">Net Income for the period</td><td class="px-6 py-3 text-sm text-right">' . number_format($net_income, 2) . '</td><td class="px-6 py-3"></td></tr>';
        
        $total_equity = $categories[3]['total'] + $net_income;
        $html .= '<tr class="font-bold border-b border-gray-200"><td class="px-6 py-3 text-sm text-gray-900 pl-6 uppercase text-indigo-700">Total Equity</td><td class="px-6 py-3 text-sm text-right text-indigo-600">' . number_format($total_equity, 2) . '</td><td class="px-6 py-3"></td></tr>';

        $total_liabilities_equity = $categories[2]['total'] + $total_equity;
        $html .= '<tr class="bg-indigo-600 text-white font-extrabold"><td class="px-6 py-4 text-md uppercase">Total Liabilities & Equity</td><td class="px-6 py-4"></td><td class="px-6 py-4 text-right text-md">' . number_format($total_liabilities_equity, 2) . '</td></tr>';

        echo $html;
        wp_die();
    }

    public function handle_search_ledger_report() {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            echo '<tr><td colspan="6" class="px-6 py-10 text-center text-red-500">Access denied.</td></tr>';
            wp_die();
        }

        global $wpdb;

        $account_id = isset($_POST['account_id']) ? intval($_POST['account_id']) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-01');
        $end_date   = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : date('Y-m-d');

        if (!$account_id) {
            echo '<tr><td colspan="6" class="px-6 py-10 text-center text-gray-500">Please select an account.</td></tr>';
            wp_die();
        }

        $je_table    = "{$wpdb->prefix}orabooks_ac_journal_entry";
        $jl_table    = "{$wpdb->prefix}orabooks_ac_journal_line";
        $coa_table   = "{$wpdb->prefix}orabooks_ac_coa_list";

        $opening_sql = $wpdb->prepare("\n            SELECT \n                coal.coa_type_id,\n                COALESCE(SUM(CASE WHEN je.status = 'Posted' AND je.entry_date < %s THEN jl.debit ELSE 0 END), 0) as total_debit,\n                COALESCE(SUM(CASE WHEN je.status = 'Posted' AND je.entry_date < %s THEN jl.credit ELSE 0 END), 0) as total_credit\n            FROM $coa_table coal\n            LEFT JOIN $jl_table jl ON coal.id = jl.account_id\n            LEFT JOIN $je_table je ON jl.journal_entry_id = je.id\n            WHERE coal.id = %d\n            GROUP BY coal.id\n        ", $start_date, $start_date, $account_id);
        
        $opening = $wpdb->get_row($opening_sql);
        $total_opening = 0;
        $type_id = 1;
        
        if ($opening) {
            $type_id = intval($opening->coa_type_id);
            if (in_array($type_id, [1, 5, 6])) {
                $total_opening = floatval($opening->total_debit) - floatval($opening->total_credit);
            } else {
                $total_opening = floatval($opening->total_credit) - floatval($opening->total_debit);
            }
        }

        $sql = $wpdb->prepare("
            SELECT 
                je.entry_date,
                je.reference_no,
                je.source_type as je_particulars,
                jl.debit,
                jl.credit,
                jl.description as line_desc
            FROM $jl_table jl
            JOIN $je_table je ON jl.journal_entry_id = je.id
            WHERE jl.account_id = %d 
              AND je.status = 'Posted' 
              AND je.entry_date >= %s 
              AND je.entry_date <= %s
            ORDER BY je.entry_date ASC, je.id ASC
        ", $account_id, $start_date, $end_date);
        
        $rows = $wpdb->get_results($sql);

        $html = '';
        $html .= '<tr class="bg-indigo-50 font-medium">
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . esc_html($start_date) . '</td>
            <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 italic">Opening Balance</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">-</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">-</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-indigo-700">' . number_format($total_opening, 2) . '</td>
        </tr>';

        $running_balance = $total_opening;
        $total_debit = 0;
        $total_credit = 0;

        if ($rows) {
            foreach ($rows as $r) {
                $debit = floatval($r->debit);
                $credit = floatval($r->credit);
                $total_debit += $debit;
                $total_credit += $credit;

                if (in_array($type_id, [1, 5, 6])) {
                    $running_balance += ($debit - $credit);
                } else {
                    $running_balance += ($credit - $debit);
                }

                $particulars = !empty($r->line_desc) ? $r->line_desc : (!empty($r->je_particulars) ? $r->je_particulars : 'Journal Entry');

                $html .= '<tr class="hover:bg-gray-50 transition-colors border-b border-gray-100">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->entry_date) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . esc_html($particulars) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->reference_no) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">' . ($debit > 0 ? number_format($debit, 2) : '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">' . ($credit > 0 ? number_format($credit, 2) : '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900">' . number_format($running_balance, 2) . '</td>
                </tr>';
            }
        }

        $html .= '<tr class="bg-gray-100 font-bold">
            <td colspan="3" class="px-6 py-4 text-sm text-right uppercase">Totals for period</td>
            <td class="px-6 py-4 text-right text-sm">' . number_format($total_debit, 2) . '</td>
            <td class="px-6 py-4 text-right text-sm">' . number_format($total_credit, 2) . '</td>
            <td class="px-6 py-4 text-right text-sm text-indigo-700">' . number_format($running_balance, 2) . '</td>
        </tr>';

        echo $html;
        wp_die();
    }
}

new OBN_Reports();
