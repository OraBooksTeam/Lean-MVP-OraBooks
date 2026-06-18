<?php
/**
 * Adapters delegating legacy accounting UI to Lean MVP service classes.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OBN_Fiscal_Adapter {
    public static function can_post($org_id, $transaction_date) {
        if (class_exists('OraBooks_Fiscal') && method_exists('OraBooks_Fiscal', 'can_post')) {
            $result = OraBooks_Fiscal::can_post((int) $org_id, $transaction_date);
            if (is_wp_error($result)) {
                return new WP_Error(
                    'fiscal_period_locked',
                    $result->get_error_message(),
                    ['status' => 409]
                );
            }
            return true;
        }

        return class_exists('OBN_Fiscal_Period_Posting_Guard')
            ? OBN_Fiscal_Period_Posting_Guard::can_post_legacy($org_id, $transaction_date)
            : true;
    }

    public static function list_periods($org_id) {
        if (class_exists('OraBooks_Fiscal') && method_exists('OraBooks_Fiscal', 'list_periods')) {
            return OraBooks_Fiscal::list_periods((int) $org_id);
        }

        $repo = new OBN_Fiscal_Period_Repository();
        return $repo->paginate((int) $org_id, ['per_page' => 100, 'page' => 1]);
    }
}

class OBN_Financial_Reports_Adapter {
    public static function is_available() {
        return class_exists('OraBooks_Financial_Reports')
            && method_exists('OraBooks_Financial_Reports', 'generate_report');
    }

    public static function generate($report_type, $period_start, $period_end, $org_id = 0) {
        if (!self::is_available()) {
            return new WP_Error('lean_reports_unavailable', 'Lean MVP financial reports are not available.');
        }

        $org_id = $org_id ?: obn_current_org_id();
        $user_id = function_exists('orabooks_get_current_user_id')
            ? orabooks_get_current_user_id()
            : get_current_user_id();

        return OraBooks_Financial_Reports::generate_report(
            (int) $org_id,
            sanitize_key($report_type),
            sanitize_text_field($period_start),
            sanitize_text_field($period_end),
            ['generated_by' => (int) $user_id]
        );
    }

    public static function map_legacy_type($legacy_type) {
        $map = [
            'income_statement' => 'profit_loss',
            'profit_loss' => 'profit_loss',
            'balance_sheet' => 'balance_sheet',
            'trial_balance' => 'trial_balance',
            'cash_flow' => 'cash_flow',
            'ledger' => 'trial_balance',
        ];

        return $map[$legacy_type] ?? '';
    }

    public static function render_trial_balance_rows($start_date, $end_date, $org_id = 0) {
        $result = self::generate('trial_balance', $start_date, $end_date, $org_id);
        if (is_wp_error($result)) {
            return $result;
        }

        $rows = [];
        $data = $result['data'] ?? $result;
        $accounts = $data['accounts'] ?? $data['rows'] ?? [];

        foreach ($accounts as $index => $row) {
            $row = (object) $row;
            $rows[] = (object) [
                'account_code' => $row->account_code ?? $row->code ?? '',
                'account_name' => $row->account_name ?? $row->name ?? '',
                'total_debit' => $row->debit ?? $row->total_debit ?? 0,
                'total_credit' => $row->credit ?? $row->total_credit ?? 0,
                'index' => $index + 1,
            ];
        }

        return $rows;
    }

    public static function render_profit_loss_rows($start_date, $end_date, $org_id = 0) {
        $result = self::generate('profit_loss', $start_date, $end_date, $org_id);
        if (is_wp_error($result)) {
            return $result;
        }

        $data = $result['data'] ?? $result;
        $rows = [];

        foreach (($data['revenue'] ?? []) as $item) {
            $item = (object) $item;
            $rows[] = (object) [
                'section' => 'Revenue',
                'account_name' => $item->account_name ?? $item->name ?? '',
                'amount' => $item->amount ?? 0,
            ];
        }
        foreach (($data['expenses'] ?? []) as $item) {
            $item = (object) $item;
            $rows[] = (object) [
                'section' => 'Expense',
                'account_name' => $item->account_name ?? $item->name ?? '',
                'amount' => $item->amount ?? 0,
            ];
        }

        return $rows;
    }

    public static function render_balance_sheet_rows($as_of_date, $org_id = 0) {
        $result = self::generate('balance_sheet', $as_of_date, $as_of_date, $org_id);
        if (is_wp_error($result)) {
            return $result;
        }

        $data = $result['data'] ?? $result;
        $rows = [];
        foreach (['assets', 'liabilities', 'equity'] as $section) {
            foreach (($data[$section] ?? []) as $item) {
                $item = (object) $item;
                $rows[] = (object) [
                    'section' => ucfirst($section),
                    'account_name' => $item->account_name ?? $item->name ?? '',
                    'amount' => $item->amount ?? 0,
                ];
            }
        }

        return $rows;
    }
}

class OBN_Expenses_Adapter {
    public static function is_available() {
        return class_exists('OraBooks_Expenses');
    }
}

class OBN_Customers_Adapter {
    public static function is_available() {
        return class_exists('OraBooks_Customers');
    }
}

class OBN_Vendors_Adapter {
    public static function is_available() {
        return class_exists('OraBooks_Vendors');
    }
}

class OBN_Inventory_Adapter {
    public static function is_available() {
        return class_exists('OraBooks_Inventory');
    }
}

class OBN_COA_Adapter {
    public static function is_available() {
        return class_exists('OraBooks_COA');
    }
}

class OBN_Posting_Adapter {
    public static function is_available() {
        return class_exists('OraBooks_Posting');
    }
}
