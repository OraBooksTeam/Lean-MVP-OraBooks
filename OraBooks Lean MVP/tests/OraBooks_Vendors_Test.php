<?php
/**
 * Unit Tests for OraBooks_Vendors (SL-027)
 *
 * Covers vendor master data, bill lifecycle, FIFO payment allocation,
 * credit note governance, and AP aging buckets.
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Vendors_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['orabooks_test_current_user_id'] = 1;
        $GLOBALS['orabooks_test_use_insert_id'] = null;

        $_POST = [];
        $_GET = [];

        global $wpdb;
        $wpdb->test_get_var_callback = null;
        $wpdb->test_get_row_callback = null;
        $wpdb->test_get_results_callback = null;
        $wpdb->test_insert_callback = null;
        $wpdb->test_query_callback = null;
        $wpdb->insert_id = 0;
        $wpdb->last_query = '';
        $wpdb->last_result = [];
    }

    private function mockVendor(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 10,
            'org_id' => 5,
            'name' => 'ABC Supplies',
            'email' => 'ap@abc.example',
            'payment_terms' => 30,
            'default_currency' => 'USD',
            'auto_apply_credit' => 1,
            'payable_balance' => '0.00',
            'credit_balance' => '0.00',
            'is_active' => 1,
        ], $overrides);
    }

    private function mockBill(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 100,
            'org_id' => 5,
            'vendor_id' => 10,
            'bill_number' => 'BILL-2026-000001',
            'bill_date' => '2026-06-01',
            'transaction_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'description' => 'Office supplies',
            'subtotal_amount' => '100.00',
            'tax_amount' => '0.00',
            'total_amount' => '100.00',
            'paid_amount' => '0.00',
            'currency' => 'USD',
            'workflow_status' => 'draft',
            'payment_status' => 'unpaid',
            'lock_status' => 'unlocked',
            'vendor_name' => 'ABC Supplies',
            'vendor_email' => 'ap@abc.example',
        ], $overrides);
    }

    #[Test]
    public function test_get_create_table_sql_contains_required_ap_tables()
    {
        $sql = implode("\n", OraBooks_Vendors::get_create_table_sql());

        $this->assertStringContainsString('orabooks_vendors', $sql);
        $this->assertStringContainsString('orabooks_bills', $sql);
        $this->assertStringContainsString('orabooks_vendor_payments', $sql);
        $this->assertStringContainsString('orabooks_vendor_payment_allocations', $sql);
        $this->assertStringContainsString('orabooks_vendor_credit_notes', $sql);
        $this->assertStringContainsString('orabooks_vendor_statement_snapshots', $sql);
    }

    #[Test]
    public function test_create_vendor_inserts_and_returns_vendor()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'orabooks_vendors') !== false) {
                return $this->mockVendor(['id' => 88, 'name' => 'New Vendor']);
            }
            return null;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 88;

        $vendor = OraBooks_Vendors::create_vendor(5, [
            'name' => 'New Vendor',
            'email' => 'vendor@example.com',
            'payment_terms' => 15,
        ]);

        $this->assertIsObject($vendor);
        $this->assertEquals(88, $vendor->id);
        $this->assertEquals('New Vendor', $vendor->name);
    }

    #[Test]
    public function test_create_bill_creates_draft_bill()
    {
        global $wpdb;

        $wpdb->test_get_var_callback = function ($query) {
            return 0;
        };
        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'orabooks_vendors') !== false && stripos($query, 'JOIN') === false) {
                return $this->mockVendor(['payment_terms' => 20]);
            }
            if (stripos($query, 'orabooks_bills') !== false) {
                return $this->mockBill([
                    'id' => 200,
                    'bill_number' => 'BILL-2026-000005',
                    'total_amount' => '120.00',
                    'workflow_status' => 'draft',
                ]);
            }
            return null;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 200;

        $bill = OraBooks_Vendors::create_bill(5, [
            'vendor_id' => 10,
            'bill_number' => 'BILL-2026-000005',
            'bill_date' => '2026-06-01',
            'subtotal_amount' => 100,
            'tax_amount' => 20,
        ]);

        $this->assertIsObject($bill);
        $this->assertEquals(200, $bill->id);
        $this->assertEquals('draft', $bill->workflow_status);
        $this->assertEquals('120.00', $bill->total_amount);
    }

    #[Test]
    public function test_create_bill_blocked_when_fiscal_period_soft_closed()
    {
        global $wpdb;

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'fiscal_periods') !== false) {
                return 'soft_closed';
            }
            return 0;
        };

        $bill = OraBooks_Vendors::create_bill(5, [
            'vendor_id' => 10,
            'bill_date' => '2026-06-01',
            'subtotal_amount' => 100,
            'tax_amount' => 20,
        ]);

        $this->assertInstanceOf(WP_Error::class, $bill);
        $this->assertEquals('fiscal_closed', $bill->get_error_code());
    }

    #[Test]
    public function test_update_bill_updates_draft_bill_fields()
    {
        global $wpdb;

        $billSelectCalls = 0;
        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'fiscal_periods') !== false) {
                return 'open';
            }
            return 0;
        };
        $wpdb->test_get_row_callback = function ($query) use (&$billSelectCalls) {
            if (stripos($query, 'orabooks_bills') !== false && stripos($query, 'JOIN') !== false) {
                $billSelectCalls++;
                if ($billSelectCalls === 1) {
                    return $this->mockBill([
                        'workflow_status' => 'draft',
                        'description' => 'Initial bill',
                        'total_amount' => '100.00',
                        'subtotal_amount' => '100.00',
                        'tax_amount' => '0.00',
                    ]);
                }

                return $this->mockBill([
                    'workflow_status' => 'draft',
                    'description' => 'Updated bill note',
                    'total_amount' => '150.00',
                    'subtotal_amount' => '150.00',
                    'tax_amount' => '0.00',
                ]);
            }
            return null;
        };

        $result = OraBooks_Vendors::update_bill(5, 100, [
            'description' => 'Updated bill note',
            'subtotal_amount' => 150,
            'tax_amount' => 0,
            'total_amount' => 150,
            'bill_date' => '2026-06-02',
            'due_days' => 20,
        ]);

        $this->assertIsObject($result);
        $this->assertEquals('Updated bill note', $result->description);
        $this->assertEquals('150.00', $result->total_amount);
    }

    #[Test]
    public function test_submit_bill_moves_draft_to_submitted()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return $this->mockBill(['workflow_status' => 'draft']);
        };
        $wpdb->test_query_callback = function () {
            return true;
        };

        $status_updated = false;
        $wpdb->test_update_callback = function ($table, $data) use (&$status_updated) {
            if (isset($data['workflow_status']) && $data['workflow_status'] === 'submitted') {
                $status_updated = true;
            }
            return true;
        };
        $wpdb->test_insert_callback = function () {
            return true;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 301;

        $result = OraBooks_Vendors::submit_bill(5, 100, 1);

        $this->assertTrue($result);
        $this->assertTrue($status_updated);
    }

    #[Test]
    public function test_approve_bill_without_auto_post_leaves_bill_approved()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'vendor_ap_configs') !== false) {
                return (object) [
                    'auto_post_bill_on_approve' => 0,
                    'adjustment_threshold' => 1000,
                    'vendor_adjustment_account' => '5000',
                ];
            }
            return $this->mockBill(['workflow_status' => 'submitted']);
        };

        $result = OraBooks_Vendors::approve_bill(5, 100, 2);

        $this->assertTrue($result);
    }

    #[Test]
    public function test_record_payment_allocates_fifo_and_stores_overpayment_credit()
    {
        global $wpdb;

        $allocationCount = 0;
        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'payment_status IN') !== false) {
                return [
                    $this->mockBill(['id' => 1, 'total_amount' => '100.00', 'paid_amount' => '20.00', 'due_date' => '2026-06-01', 'workflow_status' => 'posted']),
                    $this->mockBill(['id' => 2, 'total_amount' => '50.00', 'paid_amount' => '0.00', 'due_date' => '2026-06-15', 'workflow_status' => 'posted']),
                ];
            }
            return [];
        };
        $wpdb->test_insert_callback = function ($table, $data) use (&$allocationCount) {
            if (stripos($table, 'vendor_payment_allocations') !== false) {
                $allocationCount++;
            }
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 500;

        $result = OraBooks_Vendors::record_payment(5, 10, [
            'amount' => 200,
            'payment_date' => '2026-06-20',
        ]);

        $this->assertEquals(500, $result['payment_id']);
        $this->assertEquals(130.0, $result['allocated_amount']);
        $this->assertEquals(70.0, $result['unapplied_amount']);
        $this->assertEquals(2, $allocationCount);
    }

    #[Test]
    public function test_create_credit_note_flags_second_approval_above_threshold()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'vendor_ap_configs') !== false) {
                return (object) [
                    'auto_post_bill_on_approve' => 1,
                    'adjustment_threshold' => 500,
                    'vendor_adjustment_account' => '5300',
                ];
            }
            return null;
        };
        $wpdb->test_get_var_callback = function ($query) {
            return 0;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 321;

        $result = OraBooks_Vendors::create_credit_note(5, [
            'vendor_id' => 10,
            'amount' => 750,
            'reason' => 'Damaged shipment',
            'is_adjustment' => true,
        ]);

        $this->assertEquals(321, $result['credit_note_id']);
        $this->assertTrue($result['requires_second_approval']);
        $this->assertStringStartsWith('VCN-', $result['credit_note_number']);
    }

    #[Test]
    public function test_get_ap_aging_buckets_outstanding_bills()
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) {
            return [
                $this->mockBill(['id' => 1, 'due_date' => '2026-06-20', 'total_amount' => '100.00', 'paid_amount' => '0.00']),
                $this->mockBill(['id' => 2, 'due_date' => '2026-05-25', 'total_amount' => '200.00', 'paid_amount' => '50.00']),
                $this->mockBill(['id' => 3, 'due_date' => '2026-04-15', 'total_amount' => '300.00', 'paid_amount' => '0.00']),
                $this->mockBill(['id' => 4, 'due_date' => '2026-02-01', 'total_amount' => '400.00', 'paid_amount' => '100.00']),
            ];
        };

        $aging = OraBooks_Vendors::get_ap_aging(5, '2026-06-30');

        $this->assertEquals(0.0, $aging['current']);
        $this->assertEquals(100.0, $aging['30']);
        $this->assertEquals(150.0, $aging['60']);
        $this->assertEquals(600.0, $aging['90_plus']);
    }

    #[Test]
    public function test_create_bill_calculates_tax_from_subtotal()
    {
        global $wpdb;

        $wpdb->test_get_var_callback = function () {
            return 0;
        };
        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'tax_configs') !== false) {
                return (object) [
                    'id' => 1,
                    'default_tax_rate' => '18.0000',
                    'tax_type' => 'GST',
                    'override_reasons' => null,
                ];
            }
            if (stripos($query, 'orabooks_vendors') !== false && stripos($query, 'JOIN') === false) {
                return $this->mockVendor(['payment_terms' => 30]);
            }
            if (stripos($query, 'orabooks_bills') !== false) {
                return $this->mockBill([
                    'id' => 201,
                    'subtotal_amount' => '100.00',
                    'tax_amount' => '18.00',
                    'total_amount' => '118.00',
                    'workflow_status' => 'draft',
                ]);
            }
            return null;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 201;

        $bill = OraBooks_Vendors::create_bill(5, [
            'vendor_id' => 10,
            'bill_date' => '2026-06-01',
            'subtotal_amount' => 100,
            'jurisdiction' => 'IN',
        ]);

        $this->assertIsObject($bill);
        $this->assertEquals('118.00', $bill->total_amount);
    }

    #[Test]
    public function test_post_bill_updates_workflow_to_posted()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'vendor_ap_configs') !== false) {
                return (object) [
                    'auto_post_bill_on_approve' => 0,
                    'adjustment_threshold' => 1000,
                    'vendor_adjustment_account' => '5000',
                    'ap_account_code' => '2000',
                    'expense_account_code' => '5000',
                    'cash_account_code' => '1000',
                ];
            }
            if (stripos($query, 'orabooks_bills') !== false) {
                return $this->mockBill(['workflow_status' => 'approved']);
            }
            if (stripos($query, 'SUM(debit_amount)') !== false) {
                return (object) [
                    'total_debit' => '100.00',
                    'total_credit' => '100.00',
                ];
            }
            if (stripos($query, 'orabooks_journals') !== false) {
                return (object) [
                    'id' => 1,
                    'org_id' => 5,
                    'status' => 'draft',
                    'total_amount' => '100.00',
                    'approval_round' => 0,
                    'revision_number' => 1,
                    'source_id' => 100,
                    'source_type' => 'vendor_bill',
                    'transaction_date' => '2026-06-01',
                    'metadata' => '{}',
                ];
            }
            if (stripos($query, 'orabooks_accounts') !== false) {
                return (object) ['id' => 1, 'code' => '2000'];
            }
            return null;
        };
        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'orabooks_journal_lines') !== false) {
                return [
                    (object) ['id' => 1, 'account_id' => 1, 'debit_amount' => '100.00', 'credit_amount' => '0.00', 'description' => 'Expense', 'currency_code' => 'USD'],
                    (object) ['id' => 2, 'account_id' => 2, 'debit_amount' => '0.00', 'credit_amount' => '100.00', 'description' => 'AP', 'currency_code' => 'USD'],
                ];
            }
            return [];
        };

        $result = OraBooks_Vendors::post_bill(5, 100, 2);

        $this->assertTrue($result);
    }

    #[Test]
    public function test_void_bill_draft_success()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return $this->mockBill(['workflow_status' => 'draft']);
        };
        $wpdb->test_query_callback = function () {
            return true;
        };
        $wpdb->test_update_callback = function ($table, $data) {
            return isset($data['workflow_status']) && $data['workflow_status'] === 'void';
        };
        $wpdb->test_insert_callback = function () {
            return true;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 501;

        $result = OraBooks_Vendors::void_bill(5, 100, 1, 'Duplicate entry');

        $this->assertIsObject($result);
    }

    #[Test]
    public function test_void_bill_rejects_posted_status()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return $this->mockBill(['workflow_status' => 'posted']);
        };

        $result = OraBooks_Vendors::void_bill(5, 100, 1);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_status', $result->get_error_code());
    }

    #[Test]
    public function test_void_bill_rejects_partial_payment()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return $this->mockBill([
                'workflow_status' => 'approved',
                'payment_status' => 'partial',
                'paid_amount' => '50.00',
            ]);
        };

        $result = OraBooks_Vendors::void_bill(5, 100, 1);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('has_payments', $result->get_error_code());
    }
}
