<?php
if (!defined('ABSPATH'))
    exit;

class Frontend_Inventory_Reports
{

    public function __construct()
    {
        add_action('wp_ajax_search_sales_report', [$this, 'handle_search_sales_report']);
        add_action('wp_ajax_search_stock_report', [$this, 'handle_search_stock_report']);
        add_action('wp_ajax_search_profit_loss_report', [$this, 'handle_search_profit_loss_report']);
        add_action('wp_ajax_search_customer_due_report', [$this, 'handle_search_customer_due_report']);
        add_action('wp_ajax_search_sales_payment_report', [$this, 'handle_search_sales_payment_report']);
        add_action('wp_ajax_search_customer_payment_report', [$this, 'handle_search_customer_payment_report']);
        add_action('wp_ajax_search_supplier_payment_report', [$this, 'handle_search_supplier_payment_report']);
        add_action('wp_ajax_search_supplier_due_report', [$this, 'handle_search_supplier_due_report']);
        add_action('wp_ajax_search_sales_summary_report', [$this, 'handle_search_sales_summary_report']);
        add_action('wp_ajax_search_stock_transfer_report', [$this, 'handle_search_stock_transfer_report']);
        add_action('wp_ajax_search_journal_report', [$this, 'handle_search_journal_report']);
        add_action('wp_ajax_search_trial_balance_report', [$this, 'handle_search_trial_balance_report']);
        add_action('wp_ajax_search_income_statement_report', [$this, 'handle_search_income_statement_report']);
        add_action('wp_ajax_search_balance_sheet_report', [$this, 'handle_search_balance_sheet_report']);
        add_action('wp_ajax_search_ledger_report', [$this, 'handle_search_ledger_report']);
    }

    public function handle_search_sales_report()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        global $wpdb;

        $start = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $customer = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        $user = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $warehouse = isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0;

        $where = "WHERE s.status = 1";

        if ($start)
            $where .= $wpdb->prepare(" AND DATE(s.sales_date) >= %s", $start);
        if ($end)
            $where .= $wpdb->prepare(" AND DATE(s.sales_date) <= %s", $end);
        if ($customer)
            $where .= $wpdb->prepare(" AND s.customer_id = %d", $customer);
        if ($user)
            $where .= $wpdb->prepare(" AND s.created_by = %d", $user);
        if ($warehouse)
            $where .= $wpdb->prepare(" AND s.warehouse_id = %d", $warehouse);

        $sql = "SELECT s.*, 
                       c.customer_name, 
                       c.customer_code,
                       w.warehouse_name, 
                       u.display_name
                FROM {$wpdb->prefix}orabooks_db_sales s
                LEFT JOIN {$wpdb->prefix}orabooks_db_customers c ON s.customer_id = c.id
                LEFT JOIN {$wpdb->prefix}orabooks_db_warehouse w ON s.warehouse_id = w.id
                LEFT JOIN {$wpdb->users} u ON s.created_by = u.ID
                $where
                ORDER BY s.sales_date DESC";

        $rows = $wpdb->get_results($sql);
        $currency = '৳';

        if ($rows) {
            $i = 1;
            foreach ($rows as $r) {
                $due = floatval($r->grand_total) - floatval($r->paid_amount);
                $date = date('d-m-Y', strtotime($r->sales_date));

                echo '<tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . $i . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . esc_html($r->warehouse_name ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">' . esc_html($r->sales_code) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . $date . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . esc_html($r->customer_name ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900 grand-total">' . number_format($r->grand_total, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-green-600 paid-amount">' . number_format($r->paid_amount, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-600 due-amount">' . number_format($due, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->display_name ?? '-') . '</td>
                </tr>';
                $i++;
            }
        } else {
            echo '<tr><td colspan="9" class="px-6 py-10 text-center text-gray-500">No records found</td></tr>';
        }
        wp_die();
    }

    public function handle_search_journal_report()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if (!orabooks_can_access_inventory()) {
            echo '<tr><td colspan="8" class="px-6 py-10 text-center text-red-500">Access denied.</td></tr>';
            wp_die();
        }

        global $wpdb;

        $start = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $source_type = isset($_POST['source_type']) ? sanitize_text_field($_POST['source_type']) : '';
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        // Check if tables exist
        $je_table = "{$wpdb->prefix}orabooks_ac_journal_entry";
        $jl_table = "{$wpdb->prefix}orabooks_ac_journal_line";
        $coa_table = "{$wpdb->prefix}orabooks_ac_coa_list";

        if ($wpdb->get_var("SHOW TABLES LIKE '$je_table'") != $je_table) {
            echo '<tr><td colspan="8" class="px-6 py-10 text-center text-red-500">Journal Entry table missing. Please ensure Accounting plugin is active.</td></tr>';
            wp_die();
        }

        $where = "WHERE 1=1";
        if (!empty($start))
            $where .= $wpdb->prepare(" AND je.entry_date >= %s", $start);
        if (!empty($end))
            $where .= $wpdb->prepare(" AND je.entry_date <= %s", $end);
        if (!empty($source_type))
            $where .= $wpdb->prepare(" AND je.source_type = %s", $source_type);
        if ($user_id > 0)
            $where .= $wpdb->prepare(" AND je.created_by = %d", $user_id);

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
            echo '<tr><td colspan="8" class="px-6 py-10 text-center text-red-500">Database Error: ' . esc_html($wpdb->last_error) . '<br>Query: ' . esc_html($sql) . '</td></tr>';
            wp_die();
        }

        if ($rows) {
            $prev_je_id = null;
            $row_idx = 1;
            foreach ($rows as $r) {
                $is_new_je = ($prev_je_id !== $r->je_id);
                $date_disp = $is_new_je ? date('d-m-Y', strtotime($r->entry_date)) : '';
                $num_disp = $is_new_je ? esc_html($r->entry_number) : '';
                $ref_disp = $is_new_je ? esc_html($r->reference_no) : '';

                $acc_info = esc_html(($r->account_name ?: 'Unknown') . ' (' . ($r->account_code ?: 'N/A') . ')');
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

    public function handle_search_stock_report()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        global $wpdb;

        $warehouse = isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0;
        $brand = isset($_POST['brand_id']) ? intval($_POST['brand_id']) : 0;
        $category = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

        $where = "WHERE i.status = 1";

        if ($brand)
            $where .= $wpdb->prepare(" AND i.brand_id = %d", $brand);
        if ($category)
            $where .= $wpdb->prepare(" AND i.category_id = %d", $category);
        if ($item_id)
            $where .= $wpdb->prepare(" AND i.id = %d", $item_id);

        // If warehouse is selected, we filter by that warehouse's stock.
        // If not, we sum all stocks.

        $stock_select = "COALESCE(SUM(w.available_qty), 0)";
        $stock_join = "LEFT JOIN {$wpdb->prefix}orabooks_db_warehouseitems w ON i.id = w.item_id";

        if ($warehouse) {
            $stock_select = "COALESCE(w.available_qty, 0)";
            $stock_join .= $wpdb->prepare(" AND w.warehouse_id = %d", $warehouse);
        }

        $sql = "SELECT i.*, 
                       b.brand_name, 
                       c.category_name,
                       t.tax_name,
                       t.tax as tax_percent,
                       $stock_select as current_stock
                FROM {$wpdb->prefix}orabooks_db_items i
                LEFT JOIN {$wpdb->prefix}orabooks_db_brands b ON i.brand_id = b.id
                LEFT JOIN {$wpdb->prefix}orabooks_db_category c ON i.category_id = c.id
                LEFT JOIN {$wpdb->prefix}orabooks_db_tax t ON i.tax_id = t.id
                $stock_join
                $where
                GROUP BY i.id
                ORDER BY i.item_name ASC";

        $rows = $wpdb->get_results($sql);

        if ($rows) {
            $i = 1;
            foreach ($rows as $r) {
                $stock = floatval($r->current_stock);
                $purchase_price = floatval($r->purchase_price);
                $sales_price = floatval($r->sales_price);

                $stock_value_purchase = $stock * $purchase_price;
                $stock_value_sales = $stock * $sales_price;

                echo '<tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">' . $i . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . esc_html($r->item_code) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . esc_html($r->item_name) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->brand_name ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->category_name ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">' . number_format($r->price, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->tax_name ?? '-') . ' (' . number_format($r->tax_percent ?? 0, 2) . '%)</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right purchase-cost">' . number_format($purchase_price, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right sales-price">' . number_format($sales_price, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center font-medium current-stock">' . number_format($stock, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-blue-600 stock-value-sales">' . number_format($stock_value_sales, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-green-600 stock-value-purchase">' . number_format($stock_value_purchase, 2) . '</td>
                </tr>';
                $i++;
            }
        } else {
            echo '<tr><td colspan="12" class="px-6 py-10 text-center text-gray-500">No records found</td></tr>';
        }
        wp_die();
    }

    public function handle_search_profit_loss_report()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        global $wpdb;

        $start = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $customer = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        $user = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $warehouse = isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0;
        $category = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $brand = isset($_POST['brand_id']) ? intval($_POST['brand_id']) : 0;
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

        $where = "WHERE s.status = 1";

        if ($start)
            $where .= $wpdb->prepare(" AND DATE(s.sales_date) >= %s", $start);
        if ($end)
            $where .= $wpdb->prepare(" AND DATE(s.sales_date) <= %s", $end);
        if ($customer)
            $where .= $wpdb->prepare(" AND s.customer_id = %d", $customer);
        if ($user)
            $where .= $wpdb->prepare(" AND s.created_by = %d", $user);
        if ($warehouse)
            $where .= $wpdb->prepare(" AND s.warehouse_id = %d", $warehouse);
        if ($category)
            $where .= $wpdb->prepare(" AND it.category_id = %d", $category);
        if ($brand)
            $where .= $wpdb->prepare(" AND it.brand_id = %d", $brand);
        if ($item_id)
            $where .= $wpdb->prepare(" AND si.item_id = %d", $item_id);

        $sql = "SELECT si.*, 
                       s.sales_code, 
                       s.sales_date,
                       c.customer_name, 
                       w.warehouse_name, 
                       u.display_name,
                       it.item_name,
                       cat.category_name,
                       br.brand_name
                FROM {$wpdb->prefix}orabooks_db_salesitems si
                JOIN {$wpdb->prefix}orabooks_db_sales s ON si.sales_id = s.id
                LEFT JOIN {$wpdb->prefix}orabooks_db_customers c ON s.customer_id = c.id
                LEFT JOIN {$wpdb->prefix}orabooks_db_warehouse w ON s.warehouse_id = w.id
                LEFT JOIN {$wpdb->users} u ON s.created_by = u.ID
                JOIN {$wpdb->prefix}orabooks_db_items it ON si.item_id = it.id
                LEFT JOIN {$wpdb->prefix}orabooks_db_category cat ON it.category_id = cat.id
                LEFT JOIN {$wpdb->prefix}orabooks_db_brands br ON it.brand_id = br.id
                $where
                ORDER BY s.sales_date DESC, s.id DESC";

        $rows = $wpdb->get_results($sql);

        if ($rows) {
            $i = 1;
            foreach ($rows as $r) {
                $sales_price = floatval($r->price_per_unit);
                $purchase_cost = floatval($r->purchase_price);
                $qty = floatval($r->sales_qty);
                $tax_amt = floatval($r->tax_amt);

                $gross_profit = ($sales_price - $purchase_cost) * $qty;
                $net_profit = $gross_profit - $tax_amt;

                echo '<tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">' . $i . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">' . esc_html($r->sales_code) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . date('d-m-Y', strtotime($r->sales_date)) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . esc_html($r->item_name) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->category_name ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->brand_name ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center qty">' . number_format($qty, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right sales-price">' . number_format($sales_price, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right purchase-cost">' . number_format($purchase_cost, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium gross-profit">' . number_format($gross_profit, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500 tax-amt">' . number_format($tax_amt, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold ' . ($net_profit >= 0 ? 'text-green-600' : 'text-red-600') . ' net-profit">' . number_format($net_profit, 2) . '</td>
                </tr>';
                $i++;
            }
        } else {
            echo '<tr><td colspan="12" class="px-6 py-10 text-center text-gray-500">No records found</td></tr>';
        }
        wp_die();
    }

    public function handle_search_customer_due_report()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        global $wpdb;

        $start = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $customer = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;

        $date_filter_sales = "";
        $date_filter_pay = "";
        if ($start) {
            $date_filter_sales .= $wpdb->prepare(" AND sales_date >= %s", $start);
            $date_filter_pay .= $wpdb->prepare(" AND payment_date >= %s", $start);
        }
        if ($end) {
            $date_filter_sales .= $wpdb->prepare(" AND sales_date <= %s", $end);
            $date_filter_pay .= $wpdb->prepare(" AND payment_date <= %s", $end);
        }

        $sql = "SELECT c.id as customer_id,
                       c.customer_name,
                       c.mobile,
                       c.address,
                       c.opening_balance,
                       (SELECT COALESCE(SUM(grand_total), 0) FROM {$wpdb->prefix}orabooks_db_sales WHERE customer_id = c.id AND status = 1 $date_filter_sales) as total_sales,
                       (SELECT COALESCE(SUM(payment), 0) FROM {$wpdb->prefix}orabooks_db_salespayments WHERE customer_id = c.id AND status = 1 $date_filter_pay) as total_paid
                FROM {$wpdb->prefix}orabooks_db_customers c
                WHERE c.status = 1";
        
        if ($customer) {
            $sql .= $wpdb->prepare(" AND c.id = %d", $customer);
        }

        $rows = $wpdb->get_results($sql);

        if ($rows) {
            $i = 1;
            foreach ($rows as $r) {
                $total = floatval($r->total_sales);
                $paid = floatval($r->total_paid);
                $opening = floatval($r->opening_balance);
                $due = ($opening + $total) - $paid;

                if ($due <= 0) continue;

                echo '<tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">' . $i . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">' . esc_html($r->customer_name ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->mobile ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->address ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900 sales-amount">' . number_format($total + $opening, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-green-600 paid-amount">' . number_format($paid, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-red-600 due-amount">' . number_format($due, 2) . '</td>
                </tr>';
                $i++;
            }
        } else {
            echo '<tr><td colspan="7" class="px-6 py-10 text-center text-gray-500">No records found</td></tr>';
        }

        wp_die();
    }

    public function handle_search_sales_summary_report()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        global $wpdb;

        $start = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $customer = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        $user = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $warehouse = isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0;
        $category = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $brand = isset($_POST['brand_id']) ? intval($_POST['brand_id']) : 0;
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

        $where = "WHERE s.status = 1";

        if ($start)
            $where .= $wpdb->prepare(" AND DATE(s.sales_date) >= %s", $start);
        if ($end)
            $where .= $wpdb->prepare(" AND DATE(s.sales_date) <= %s", $end);
        if ($customer)
            $where .= $wpdb->prepare(" AND s.customer_id = %d", $customer);
        if ($user)
            $where .= $wpdb->prepare(" AND s.created_by = %d", $user);
        if ($warehouse)
            $where .= $wpdb->prepare(" AND s.warehouse_id = %d", $warehouse);
        if ($category)
            $where .= $wpdb->prepare(" AND it.category_id = %d", $category);
        if ($brand)
            $where .= $wpdb->prepare(" AND it.brand_id = %d", $brand);
        if ($item_id)
            $where .= $wpdb->prepare(" AND si.item_id = %d", $item_id);

        $sql = "SELECT it.item_name,
                       cat.category_name,
                       SUM(si.sales_qty) as total_qty,
                       SUM((si.price_per_unit * si.sales_qty) + si.tax_amt - si.discount_amt) as total_amt
                FROM {$wpdb->prefix}orabooks_db_salesitems si
                JOIN {$wpdb->prefix}orabooks_db_sales s ON si.sales_id = s.id
                JOIN {$wpdb->prefix}orabooks_db_items it ON si.item_id = it.id
                LEFT JOIN {$wpdb->prefix}orabooks_db_category cat ON it.category_id = cat.id
                $where
                GROUP BY si.item_id
                ORDER BY it.item_name ASC";

        $rows = $wpdb->get_results($sql);

        if ($rows) {
            $i = 1;
            foreach ($rows as $r) {
                echo '<tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">' . $i . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . esc_html($r->item_name) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->category_name ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center qty">' . number_format($r->total_qty, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold total-amt">' . number_format($r->total_amt, 2) . '</td>
                </tr>';
                $i++;
            }
        } else {
            echo '<tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">No records found</td></tr>';
        }

        wp_die();
    }

    public function handle_search_stock_transfer_report()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        global $wpdb;

        $start = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $from_w = isset($_POST['from_warehouse_id']) ? intval($_POST['from_warehouse_id']) : 0;
        $to_w = isset($_POST['to_warehouse_id']) ? intval($_POST['to_warehouse_id']) : 0;
        $category = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $brand = isset($_POST['brand_id']) ? intval($_POST['brand_id']) : 0;
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        $where = "WHERE (st.status = 1 OR st.status IS NULL)";

        if ($start)
            $where .= $wpdb->prepare(" AND st.transfer_date >= %s", $start);
        if ($end)
            $where .= $wpdb->prepare(" AND st.transfer_date <= %s", $end);
        if ($from_w)
            $where .= $wpdb->prepare(" AND st.warehouse_from = %d", $from_w);
        if ($to_w)
            $where .= $wpdb->prepare(" AND st.warehouse_to = %d", $to_w);
        if ($category)
            $where .= $wpdb->prepare(" AND it.category_id = %d", $category);
        if ($brand)
            $where .= $wpdb->prepare(" AND it.brand_id = %d", $brand);
        if ($item_id)
            $where .= $wpdb->prepare(" AND sti.item_id = %d", $item_id);
        if ($user_id)
            $where .= $wpdb->prepare(" AND st.created_by = %d", $user_id);

        $sql = "SELECT sti.*, 
                       st.transfer_date,
                       w1.warehouse_name as from_warehouse,
                       w2.warehouse_name as to_warehouse,
                       it.item_name,
                       cat.category_name,
                       br.brand_name,
                       u.display_name
                FROM {$wpdb->prefix}orabooks_db_stocktransferitems sti
                JOIN {$wpdb->prefix}orabooks_db_stocktransfer st ON sti.stocktransfer_id = st.id
                LEFT JOIN {$wpdb->prefix}orabooks_db_warehouse w1 ON st.warehouse_from = w1.id
                LEFT JOIN {$wpdb->prefix}orabooks_db_warehouse w2 ON st.warehouse_to = w2.id
                JOIN {$wpdb->prefix}orabooks_db_items it ON sti.item_id = it.id
                LEFT JOIN {$wpdb->prefix}orabooks_db_category cat ON it.category_id = cat.id
                LEFT JOIN {$wpdb->prefix}orabooks_db_brands br ON it.brand_id = br.id
                LEFT JOIN {$wpdb->users} u ON st.created_by = u.ID
                $where
                ORDER BY st.transfer_date DESC, st.id DESC";

        $rows = $wpdb->get_results($sql);

        if ($rows) {
            $i = 1;
            foreach ($rows as $r) {
                echo '<tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">' . $i . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . date('d-m-Y', strtotime($r->transfer_date)) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . esc_html($r->from_warehouse ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . esc_html($r->to_warehouse ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">' . esc_html($r->item_name) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->category_name ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->brand_name ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center font-bold qty">' . number_format($r->transfer_qty, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->display_name ?? '-') . '</td>
                </tr>';
                $i++;
            }
        } else {
            echo '<tr><td colspan="9" class="px-6 py-10 text-center text-gray-500">No records found</td></tr>';
        }

        wp_die();
    }

    public function handle_search_sales_payment_report()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        global $wpdb;

        $start = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;

        if (!$customer_id) {
            wp_send_json_error(['message' => 'Customer is mandatory']);
        }

        // Get Customer Info
        $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_customers WHERE id = %d", $customer_id));

        // Calculate Previous Due (Opening Balance + Sales - Payments) before start_date
        $opening_balance = floatval($customer->opening_balance ?? 0);

        $prev_sales = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(grand_total) FROM {$wpdb->prefix}orabooks_db_sales WHERE customer_id = %d AND status = 1 AND sales_date < %s",
            $customer_id,
            $start ? $start : '1970-01-01'
        ));

        $prev_payments = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(payment) FROM {$wpdb->prefix}orabooks_db_salespayments WHERE customer_id = %d AND status = 1 AND payment_date < %s",
            $customer_id,
            $start ? $start : '1970-01-01'
        ));

        $prev_due = $opening_balance + floatval($prev_sales) - floatval($prev_payments);

        // Fetch Sales Items, Invoice Adjustments, and Payments in Range
        $where_sales = $wpdb->prepare(" WHERE s.customer_id = %d AND s.status = 1", $customer_id);
        $where_pay = $wpdb->prepare(" WHERE customer_id = %d AND status = 1", $customer_id);

        $date_filter_sales = "";
        $date_filter_pay = "";
        if ($start) {
            $date_filter_sales .= $wpdb->prepare(" AND s.sales_date >= %s", $start);
            $date_filter_pay .= $wpdb->prepare(" AND payment_date >= %s", $start);
        }
        if ($end) {
            $date_filter_sales .= $wpdb->prepare(" AND s.sales_date <= %s", $end);
            $date_filter_pay .= $wpdb->prepare(" AND payment_date <= %s", $end);
        }

        $sql = "
            (SELECT 
                si.item_id,
                it.item_name as description,
                si.sales_qty as qty,
                si.tax_amt as tax_amt,
                si.discount_amt as discount_amt,
                (si.total_cost) as bill_amt,
                0 as receive,
                s.sales_date as activity_date,
                s.sales_code as invoice_no,
                1 as type
            FROM {$wpdb->prefix}orabooks_db_salesitems si
            JOIN {$wpdb->prefix}orabooks_db_sales s ON si.sales_id = s.id
            JOIN {$wpdb->prefix}orabooks_db_items it ON si.item_id = it.id
            $where_sales $date_filter_sales)
            
            UNION ALL
            
            (SELECT 
                0 as item_id,
                'Invoice Adjustments (Other Charges & Global Discount)' as description,
                0 as qty,
                COALESCE(s.other_charges_amt, 0) as tax_amt,
                COALESCE(s.tot_discount_to_all_amt, s.discount_to_all_input, 0) as discount_amt,
                (COALESCE(s.other_charges_amt, 0) - COALESCE(s.tot_discount_to_all_amt, s.discount_to_all_input, 0)) as bill_amt,
                0 as receive,
                s.sales_date as activity_date,
                s.sales_code as invoice_no,
                1.5 as type
            FROM {$wpdb->prefix}orabooks_db_sales s
            $where_sales $date_filter_sales
            AND (COALESCE(s.other_charges_amt, 0) != 0 OR COALESCE(s.tot_discount_to_all_amt, 0) != 0 OR COALESCE(s.discount_to_all_input, 0) != 0))

            UNION ALL
            
            (SELECT 
                0 as item_id,
                COALESCE(payment_note, 'Payment Received') as description,
                0 as qty,
                0 as tax_amt,
                0 as discount_amt,
                0 as bill_amt,
                COALESCE(payment, 0) as receive,
                payment_date as activity_date,
                payment_code as invoice_no,
                2 as type
            FROM {$wpdb->prefix}orabooks_db_salespayments
            $where_pay $date_filter_pay)
            
            ORDER BY activity_date ASC, type ASC
        ";

        $rows = $wpdb->get_results($sql);
        $html = '';
        $running_total = $prev_due;

        if ($rows) {
            $i = 1;
            foreach ($rows as $r) {
                $running_total += floatval($r->bill_amt) - floatval($r->receive);

                $html .= '<tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">' . $i . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . date('d-m-Y', strtotime($r->activity_date)) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">' . esc_html($r->invoice_no) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . esc_html($r->description) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">' . ($r->qty > 0 ? number_format($r->qty, 2) : '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-600 tax-amt">' . ($r->tax_amt != 0 ? number_format($r->tax_amt, 2) : '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-600 discount-amt">' . ($r->discount_amt != 0 ? number_format($r->discount_amt, 2) : '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right bill-amt">' . ($r->bill_amt != 0 ? number_format($r->bill_amt, 2) : '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right receive-amt">' . ($r->receive > 0 ? number_format($r->receive, 2) : '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold total-amt">' . number_format($running_total, 2) . '</td>
                </tr>';
                $i++;
            }
        } else {
            $html = '<tr><td colspan="10" class="px-6 py-10 text-center text-gray-500">No records found</td></tr>';
        }

        wp_send_json_success([
            'customer_name' => $customer->customer_name ?? '-',
            'contact' => $customer->mobile ?? '-',
            'address' => $customer->address ?? '-',
            'prev_due' => number_format($prev_due, 2),
            'html' => $html,
            'footer_bill' => number_format(array_sum(array_column($rows, 'bill_amt')), 2),
            'footer_recv' => number_format(array_sum(array_column($rows, 'receive')), 2),
            'footer_tax' => number_format(array_sum(array_column($rows, 'tax_amt')), 2),
            'footer_discount' => number_format(array_sum(array_column($rows, 'discount_amt')), 2),
            'footer_total' => number_format($running_total, 2)
        ]);
    }

    public function handle_search_customer_payment_report()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        global $wpdb;

        $start = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        $payment_type = isset($_POST['payment_type']) ? sanitize_text_field($_POST['payment_type']) : '';
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        $where = " WHERE sp.status = 1";

        if ($start) {
            $where .= $wpdb->prepare(" AND sp.payment_date >= %s", $start);
        }
        if ($end) {
            $where .= $wpdb->prepare(" AND sp.payment_date <= %s", $end);
        }
        if ($customer_id) {
            $where .= $wpdb->prepare(" AND sp.customer_id = %d", $customer_id);
        }
        if ($payment_type) {
            $where .= $wpdb->prepare(" AND sp.payment_type = %s", $payment_type);
        }
        if ($user_id) {
            $where .= $wpdb->prepare(" AND sp.created_by = %d", $user_id);
        }

        $sql = "
            SELECT 
                sp.*,
                s.sales_code as invoice_no,
                c.customer_name,
                c.customer_code,
                u.display_name
            FROM {$wpdb->prefix}orabooks_db_salespayments sp
            LEFT JOIN {$wpdb->prefix}orabooks_db_sales s ON sp.sales_id = s.id
            LEFT JOIN {$wpdb->prefix}orabooks_db_customers c ON sp.customer_id = c.id
            LEFT JOIN {$wpdb->prefix}users u ON sp.created_by = u.ID
            $where
            ORDER BY sp.payment_date DESC, sp.id DESC
        ";

        $results = $wpdb->get_results($sql);
        $html = '';

        if ($results) {
            $i = 1;
            foreach ($results as $r) {
                $html .= '<tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">' . $i . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">' . esc_html($r->invoice_no ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . date('d-m-Y', strtotime($r->payment_date)) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->customer_code ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">' . esc_html($r->customer_name ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->payment_type) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 max-w-xs truncate" title="' . esc_attr($r->payment_note) . '">' . esc_html($r->payment_note) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-green-600 payment-amt">' . number_format($r->payment, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->display_name ?? 'System') . '</td>
                </tr>';
                $i++;
            }
        } else {
            $html = '<tr><td colspan="9" class="px-6 py-10 text-center text-gray-500">No records found</td></tr>';
        }

        echo $html;
        wp_die();
    }

    public function handle_search_supplier_payment_report()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        global $wpdb;

        $start = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;
        $payment_type = isset($_POST['payment_type']) ? sanitize_text_field($_POST['payment_type']) : '';
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        $where = " WHERE pp.status = 1";

        if ($start) {
            $where .= $wpdb->prepare(" AND pp.payment_date >= %s", $start);
        }
        if ($end) {
            $where .= $wpdb->prepare(" AND pp.payment_date <= %s", $end);
        }
        if ($supplier_id) {
            $where .= $wpdb->prepare(" AND (pp.supplier_id = %d OR p.supplier_id = %d)", $supplier_id, $supplier_id);
        }
        if ($payment_type) {
            $where .= $wpdb->prepare(" AND pp.payment_type = %s", $payment_type);
        }
        if ($user_id) {
            $where .= $wpdb->prepare(" AND pp.created_by = %d", $user_id);
        }

        $sql = "
            SELECT 
                pp.*,
                p.purchase_code,
                COALESCE(s_pp.supplier_name, s_p.supplier_name) as supplier_name,
                COALESCE(s_pp.supplier_code, s_p.supplier_code) as supplier_code,
                u.display_name
            FROM {$wpdb->prefix}orabooks_db_purchasepayments pp
            LEFT JOIN {$wpdb->prefix}orabooks_db_purchase p ON pp.purchase_id = p.id
            LEFT JOIN {$wpdb->prefix}orabooks_db_suppliers s_pp ON pp.supplier_id = s_pp.id
            LEFT JOIN {$wpdb->prefix}orabooks_db_suppliers s_p ON p.supplier_id = s_p.id
            LEFT JOIN {$wpdb->prefix}users u ON pp.created_by = u.ID
            $where
            ORDER BY pp.payment_date DESC, pp.id DESC
        ";

        $results = $wpdb->get_results($sql);
        $html = '';

        if ($results) {
            $i = 1;
            foreach ($results as $r) {
                $html .= '<tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">' . $i . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">' . esc_html($r->purchase_code ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . date('d-m-Y', strtotime($r->payment_date)) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->supplier_code ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">' . esc_html($r->supplier_name ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->payment_type) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 max-w-xs truncate" title="' . esc_attr($r->payment_note) . '">' . esc_html($r->payment_note) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-red-600 payment-amt">' . number_format($r->payment, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->display_name ?? 'System') . '</td>
                </tr>';
                $i++;
            }
        } else {
            $html = '<tr><td colspan="9" class="px-6 py-10 text-center text-gray-500">No records found</td></tr>';
        }

        echo $html;
        wp_die();
    }

    public function handle_search_supplier_due_report()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        global $wpdb;

        $start = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;

        $date_filter_purchase = "";
        $date_filter_pay = "";
        if ($start) {
            $date_filter_purchase .= $wpdb->prepare(" AND purchase_date >= %s", $start);
            $date_filter_pay .= $wpdb->prepare(" AND payment_date >= %s", $start);
        }
        if ($end) {
            $date_filter_purchase .= $wpdb->prepare(" AND purchase_date <= %s", $end);
            $date_filter_pay .= $wpdb->prepare(" AND payment_date <= %s", $end);
        }

        $sql = "SELECT s.id,
                       s.supplier_name,
                       s.mobile,
                       s.address,
                       s.opening_balance,
                       (SELECT COALESCE(SUM(grand_total), 0) FROM {$wpdb->prefix}orabooks_db_purchase WHERE supplier_id = s.id AND status = 1 $date_filter_purchase) as total_purchase,
                       (SELECT COALESCE(SUM(payment), 0) FROM {$wpdb->prefix}orabooks_db_supplier_payments WHERE supplier_id = s.id AND status = 1 $date_filter_pay) as total_paid
                FROM {$wpdb->prefix}orabooks_db_suppliers s
                WHERE s.status = 1";
        
        if ($supplier_id) {
            $sql .= $wpdb->prepare(" AND s.id = %d", $supplier_id);
        }

        $rows = $wpdb->get_results($sql);

        if ($rows) {
            $i = 1;
            foreach ($rows as $r) {
                $total = floatval($r->total_purchase);
                $paid = floatval($r->total_paid);
                $opening = floatval($r->opening_balance);
                $due = ($total + $opening) - $paid;

                if ($due <= 0) continue;

                echo '<tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">' . $i . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">' . esc_html($r->supplier_name ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->mobile ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html($r->address ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900 purchase-amount">' . number_format($total + $opening, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-green-600 paid-amount">' . number_format($paid, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-red-600 due-amount">' . number_format($due, 2) . '</td>
                </tr>';
                $i++;
            }
        } else {
            echo '<tr><td colspan="7" class="px-6 py-10 text-center text-gray-500">No records found</td></tr>';
        }

        wp_die();
    }

    public function handle_search_trial_balance_report()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if (!orabooks_can_access_inventory()) {
            echo '<tr><td colspan="5" class="px-6 py-10 text-center text-red-500">Access denied.</td></tr>';
            wp_die();
        }

        global $wpdb;

        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-01');
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : date('Y-m-d');

        // Check if tables exist
        $je_table = "{$wpdb->prefix}orabooks_ac_journal_entry";
        $jl_table = "{$wpdb->prefix}orabooks_ac_journal_line";
        $coa_table = "{$wpdb->prefix}orabooks_ac_coa_list";

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

    public function handle_search_income_statement_report()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if (!orabooks_can_access_inventory()) {
            echo '<tr><td colspan="3" class="px-6 py-10 text-center text-red-500">Access denied.</td></tr>';
            wp_die();
        }

        global $wpdb;

        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-01');
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : date('Y-m-d');

        $je_table = "{$wpdb->prefix}orabooks_ac_journal_entry";
        $jl_table = "{$wpdb->prefix}orabooks_ac_journal_line";
        $coa_table = "{$wpdb->prefix}orabooks_ac_coa_list";
        $types_table = "{$wpdb->prefix}orabooks_ac_coa_types";

        if ($wpdb->get_var("SHOW TABLES LIKE '$coa_table'") != $coa_table) {
            echo '<tr><td colspan="3" class="px-6 py-10 text-center text-red-500">Accounting tables missing.</td></tr>';
            wp_die();
        }

        // Mapping based on previous research: 4=Income, 5=COGS, 6=Expenses

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

                // For Income (4) and Credits, balance is Credit - Debit
                // For COGS (5) and Expenses (6), balance is Debit - Credit
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

        // Generate HTML
        $html = '';

        // 1. Revenue
        $html .= '<tr class="bg-gray-50 font-bold"><td colspan="2" class="px-6 py-3 text-sm text-gray-900 uppercase">Revenue</td><td class="px-6 py-3"></td></tr>';
        if (!empty($categories[4]['data'])) {
            foreach ($categories[4]['data'] as $item) {
                $html .= '<tr class="border-b border-gray-100"><td class="px-6 py-3 text-sm text-gray-600 pl-10">' . esc_html($item['name']) . '</td><td class="px-6 py-3 text-sm text-right">' . number_format($item['amount'], 2) . '</td><td class="px-6 py-3"></td></tr>';
            }
        } else {
            $html .= '<tr><td class="px-6 py-3 text-sm text-gray-400 pl-10 italic">No revenue recorded</td><td class="px-6 py-3 text-sm text-right">0.00</td><td class="px-6 py-3"></td></tr>';
        }
        $html .= '<tr class="font-bold border-b-2 border-gray-200"><td class="px-6 py-3 text-sm text-gray-900 pl-6 uppercase">Total Revenue</td><td class="px-6 py-3"></td><td class="px-6 py-3 text-sm text-right">' . number_format($categories[4]['total'], 2) . '</td></tr>';

        // 2. COGS
        $html .= '<tr class="bg-gray-50 font-bold mt-4"><td colspan="2" class="px-6 py-3 text-sm text-gray-900 uppercase">Cost of Goods Sold</td><td class="px-6 py-3"></td></tr>';
        if (!empty($categories[5]['data'])) {
            foreach ($categories[5]['data'] as $item) {
                $html .= '<tr class="border-b border-gray-100"><td class="px-6 py-3 text-sm text-gray-600 pl-10">' . esc_html($item['name']) . '</td><td class="px-6 py-3 text-sm text-right">' . number_format($item['amount'], 2) . '</td><td class="px-6 py-3"></td></tr>';
            }
        } else {
            $html .= '<tr><td class="px-6 py-3 text-sm text-gray-400 pl-10 italic">No COGS recorded</td><td class="px-6 py-3 text-sm text-right">0.00</td><td class="px-6 py-3"></td></tr>';
        }
        $html .= '<tr class="font-bold border-b-2 border-gray-200"><td class="px-6 py-3 text-sm text-gray-900 pl-6 uppercase">Total COGS</td><td class="px-6 py-3"></td><td class="px-6 py-3 text-sm text-right text-red-600">' . number_format($categories[5]['total'], 2) . '</td></tr>';

        // Gross Profit
        $gross_profit = $categories[4]['total'] - $categories[5]['total'];
        $html .= '<tr class="bg-indigo-50 font-extrabold text-indigo-800"><td class="px-6 py-4 text-md uppercase">Gross Profit</td><td class="px-6 py-4"></td><td class="px-6 py-4 text-right text-md">' . number_format($gross_profit, 2) . '</td></tr>';

        // 3. Operating Expenses
        $html .= '<tr class="bg-gray-50 font-bold mt-4"><td colspan="2" class="px-6 py-3 text-sm text-gray-900 uppercase">Operating Expenses</td><td class="px-6 py-3"></td></tr>';
        if (!empty($categories[6]['data'])) {
            foreach ($categories[6]['data'] as $item) {
                $html .= '<tr class="border-b border-gray-100"><td class="px-6 py-3 text-sm text-gray-600 pl-10">' . esc_html($item['name']) . '</td><td class="px-6 py-3 text-sm text-right">' . number_format($item['amount'], 2) . '</td><td class="px-6 py-3"></td></tr>';
            }
        } else {
            $html .= '<tr><td class="px-6 py-3 text-sm text-gray-400 pl-10 italic">No expenses recorded</td><td class="px-6 py-3 text-sm text-right">0.00</td><td class="px-6 py-3"></td></tr>';
        }
        $html .= '<tr class="font-bold border-b-2 border-gray-200"><td class="px-6 py-3 text-sm text-gray-900 pl-6 uppercase">Total Operating Expenses</td><td class="px-6 py-3"></td><td class="px-6 py-3 text-sm text-right text-red-600">' . number_format($categories[6]['total'], 2) . '</td></tr>';

        // Net Profit
        $net_profit = $gross_profit - $categories[6]['total'];
        $html .= '<tr class="bg-indigo-600 text-white font-extrabold"><td class="px-6 py-4 text-md uppercase">Net Profit / Loss</td><td class="px-6 py-4"></td><td class="px-6 py-4 text-right text-md">' . number_format($net_profit, 2) . '</td></tr>';

        echo $html;
        wp_die();
    }

    public function handle_search_balance_sheet_report()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if (!orabooks_can_access_inventory()) {
            echo '<tr><td colspan="3" class="px-6 py-10 text-center text-red-500">Access denied.</td></tr>';
            wp_die();
        }

        global $wpdb;

        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-01');
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : date('Y-m-d');

        $je_table = "{$wpdb->prefix}orabooks_ac_journal_entry";
        $jl_table = "{$wpdb->prefix}orabooks_ac_journal_line";
        $coa_table = "{$wpdb->prefix}orabooks_ac_coa_list";
        $types_table = "{$wpdb->prefix}orabooks_ac_coa_types";

        if ($wpdb->get_var("SHOW TABLES LIKE '$coa_table'") != $coa_table) {
            echo '<tr><td colspan="3" class="px-6 py-10 text-center text-red-500">Accounting tables missing.</td></tr>';
            wp_die();
        }

        // 1. Calculate Net Income for the period independently
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
            if ($type == 4) {
                $net_income += ($c - $d);
            } // Revenue
            else {
                $net_income -= ($d - $c);
            } // COGS and Expenses
        }

        // 2. Fetch Assets, Liabilities, and Equity
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

                // Assets (1): Debit - Credit
                // Liabilities (2) & Equity (3): Credit - Debit
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

        // Generate HTML
        $html = '';

        // --- ASSETS ---
        $html .= '<tr class="bg-gray-50 font-bold"><td colspan="2" class="px-6 py-3 text-sm text-gray-900 uppercase">Assets</td><td class="px-6 py-3"></td></tr>';
        if (!empty($categories[1]['data'])) {
            foreach ($categories[1]['data'] as $item) {
                $html .= '<tr class="border-b border-gray-100"><td class="px-6 py-3 text-sm text-gray-600 pl-10">' . esc_html($item['name']) . '</td><td class="px-6 py-3 text-sm text-right">' . number_format($item['amount'], 2) . '</td><td class="px-6 py-3"></td></tr>';
            }
        } else {
            $html .= '<tr><td class="px-6 py-3 text-sm text-gray-400 pl-10 italic">No assets found</td><td class="px-6 py-3 text-sm text-right">0.00</td><td class="px-6 py-3"></td></tr>';
        }
        $html .= '<tr class="bg-indigo-50 font-extrabold text-indigo-700 border-b-2 border-indigo-200"><td class="px-6 py-4 text-md uppercase pl-6">Total Assets</td><td class="px-6 py-4"></td><td class="px-6 py-4 text-right text-md">' . number_format($categories[1]['total'], 2) . '</td></tr>';

        // --- LIABILITIES ---
        $html .= '<tr class="bg-gray-50 font-bold mt-4"><td colspan="2" class="px-6 py-3 text-sm text-gray-900 uppercase">Liabilities</td><td class="px-6 py-3"></td></tr>';
        if (!empty($categories[2]['data'])) {
            foreach ($categories[2]['data'] as $item) {
                $html .= '<tr class="border-b border-gray-100"><td class="px-6 py-3 text-sm text-gray-600 pl-10">' . esc_html($item['name']) . '</td><td class="px-6 py-3 text-sm text-right">' . number_format($item['amount'], 2) . '</td><td class="px-6 py-3"></td></tr>';
            }
        } else {
            $html .= '<tr><td class="px-6 py-3 text-sm text-gray-400 pl-10 italic">No liabilities found</td><td class="px-6 py-3 text-sm text-right">0.00</td><td class="px-6 py-3"></td></tr>';
        }
        $html .= '<tr class="font-bold border-b border-gray-200"><td class="px-6 py-3 text-sm text-gray-900 pl-6 uppercase text-indigo-700">Total Liabilities</td><td class="px-6 py-3 text-sm text-right text-indigo-600">' . number_format($categories[2]['total'], 2) . '</td><td class="px-6 py-3"></td></tr>';

        // --- EQUITY ---
        $html .= '<tr class="bg-gray-50 font-bold mt-4"><td colspan="2" class="px-6 py-3 text-sm text-gray-900 uppercase">Equity</td><td class="px-6 py-3"></td></tr>';
        if (!empty($categories[3]['data'])) {
            foreach ($categories[3]['data'] as $item) {
                $html .= '<tr class="border-b border-gray-100"><td class="px-6 py-3 text-sm text-gray-600 pl-10">' . esc_html($item['name']) . '</td><td class="px-6 py-3 text-sm text-right">' . number_format($item['amount'], 2) . '</td><td class="px-6 py-3"></td></tr>';
            }
        }

        // Net Income
        $html .= '<tr class="border-b border-gray-100 italic"><td class="px-6 py-3 text-sm text-gray-600 pl-10">Net Income for the period</td><td class="px-6 py-3 text-sm text-right">' . number_format($net_income, 2) . '</td><td class="px-6 py-3"></td></tr>';

        $total_equity = $categories[3]['total'] + $net_income;
        $html .= '<tr class="font-bold border-b border-gray-200"><td class="px-6 py-3 text-sm text-gray-900 pl-6 uppercase text-indigo-700">Total Equity</td><td class="px-6 py-3 text-sm text-right text-indigo-600">' . number_format($total_equity, 2) . '</td><td class="px-6 py-3"></td></tr>';

        // --- TOTAL LIABILITIES & EQUITY ---
        $total_liabilities_equity = $categories[2]['total'] + $total_equity;
        $html .= '<tr class="bg-indigo-600 text-white font-extrabold"><td class="px-6 py-4 text-md uppercase">Total Liabilities & Equity</td><td class="px-6 py-4"></td><td class="px-6 py-4 text-right text-md">' . number_format($total_liabilities_equity, 2) . '</td></tr>';

        echo $html;
        wp_die();
    }

    public function handle_search_ledger_report()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if (!orabooks_can_access_inventory()) {
            echo '<tr><td colspan="6" class="px-6 py-10 text-center text-red-500">Access denied.</td></tr>';
            wp_die();
        }

        global $wpdb;

        $account_id = isset($_POST['account_id']) ? intval($_POST['account_id']) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-01');
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : date('Y-m-d');

        if (!$account_id) {
            echo '<tr><td colspan="6" class="px-6 py-10 text-center text-gray-500">Please select an account.</td></tr>';
            wp_die();
        }

        $je_table = "{$wpdb->prefix}orabooks_ac_journal_entry";
        $jl_table = "{$wpdb->prefix}orabooks_ac_journal_line";
        $coa_table = "{$wpdb->prefix}orabooks_ac_coa_list";

        // 1. Get Opening Balance (sum up to start_date - 1)
        $opening_sql = $wpdb->prepare("\n            SELECT \n                coal.coa_type_id,\n                COALESCE(SUM(CASE WHEN je.status = 'Posted' AND je.entry_date < %s THEN jl.debit ELSE 0 END), 0) as total_debit,\n                COALESCE(SUM(CASE WHEN je.status = 'Posted' AND je.entry_date < %s THEN jl.credit ELSE 0 END), 0) as total_credit\n            FROM $coa_table coal\n            LEFT JOIN $jl_table jl ON coal.id = jl.account_id\n            LEFT JOIN $je_table je ON jl.journal_entry_id = je.id\n            WHERE coal.id = %d\n            GROUP BY coal.id\n        ", $start_date, $start_date, $account_id);

        $opening = $wpdb->get_row($opening_sql);
        $total_opening = 0;
        $type_id = 1;

        if ($opening) {
            $type_id = intval($opening->coa_type_id);
            // Asset (1), Expense (6), COGS (5): Debit - Credit
            // Liability (2), Equity (3), Income (4): Credit - Debit
            if (in_array($type_id, [1, 5, 6])) {
                $total_opening = floatval($opening->total_debit) - floatval($opening->total_credit);
            } else {
                $total_opening = floatval($opening->total_credit) - floatval($opening->total_debit);
            }
        }

        // 2. Fetch Transactions within range
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

        // HTML Output
        $html = '';

        // Opening Balance Row
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

        // Totals Row
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
