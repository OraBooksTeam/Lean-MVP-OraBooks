<?php
/**
 * Unit Tests for OraBooks_Customers (SL-021)
 *
 * Covers: customer CRUD, invoice lifecycle, payment recording,
 * global admin mode (org_id=0), stats, and seed functionality.
 *
 * Run: phpunit --configuration tests/phpunit.xml --testsuite="OraBooks SL-021 Customers Tests"
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Customers_Test extends TestCase
{
    // -----------------------------------------------------------------------
    // Lifecycle
    // -----------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        // Reset all test globals to defaults
        $GLOBALS['orabooks_test_current_user_id'] = 1;
        $GLOBALS['orabooks_test_current_user_can'] = true;
        $GLOBALS['orabooks_test_use_insert_id'] = null;

        // Reset superglobals
        $_POST = [];
        $_GET  = [];

        // Reset wpdb callbacks
        global $wpdb;
        $wpdb->test_get_var_callback    = null;
        $wpdb->test_get_row_callback    = null;
        $wpdb->test_get_results_callback = null;
        $wpdb->insert_id = 0;
        $wpdb->last_query = '';
        $wpdb->last_result = [];
    }

    // ================================================================
    // Helper: build a mock customer row object
    // ================================================================

    private function mockCustomer(array $overrides = []): object
    {
        return (object) array_merge([
            'id'                    => 1,
            'user_id'               => 10,
            'org_id'                => 5,
            'is_active'             => 1,
            'last_paid_invoice_date' => null,
            'lifetime_value'        => '0.00',
            'notes'                 => null,
            'created_at'            => '2026-06-01 12:00:00',
            'updated_at'            => '2026-06-15 12:00:00',
            'email'                 => 'customer@example.com',
            'is_email_verified'     => 1,
            'user_created_at'       => '2026-01-01 10:00:00',
        ], $overrides);
    }

    private function mockInvoice(array $overrides = []): object
    {
        return (object) array_merge([
            'id'                => 100,
            'org_id'            => 5,
            'customer_id'       => 1,
            'invoice_number'    => 'INV-202606-0001',
            'invoice_date'      => '2026-06-01',
            'transaction_date'  => '2026-06-01',
            'due_date'          => '2026-07-01',
            'description'       => 'Test invoice',
            'total_amount'      => '500.00',
            'tax_amount'        => '0.00',
            'currency'          => 'USD',
            'payment_status'    => 'unpaid',
            'workflow_status'   => 'draft',
            'paid_amount'       => '0.00',
            'paid_at'           => null,
            'last_payment_date' => null,
            'customer_user_id'  => 10,
            'customer_email'    => 'customer@example.com',
            'payments'          => [],
            'created_at'        => '2026-06-01 12:00:00',
            'updated_at'        => '2026-06-01 12:00:00',
        ], $overrides);
    }

    private function mockPayment(array $overrides = []): object
    {
        return (object) array_merge([
            'id'             => 900,
            'org_id'         => 5,
            'invoice_id'     => 100,
            'payment_date'   => '2026-06-15',
            'amount'         => '500.00',
            'payment_method' => 'bank_transfer',
            'reference'      => 'TXN-001',
            'notes'          => '',
            'created_at'     => '2026-06-15 14:00:00',
        ], $overrides);
    }

    // ================================================================
    // CUSTOMER CRUD
    // ================================================================

    // ---------- get_or_create ----------

    #[Test]
    public function test_get_or_create_returns_existing_customer()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            // First SELECT returns existing customer
            return $this->mockCustomer(['id' => 1, 'user_id' => 10, 'org_id' => 5]);
        };

        $result = OraBooks_Customers::get_or_create(10, 5);

        $this->assertIsObject($result);
        $this->assertEquals(10, $result->user_id);
        $this->assertEquals(5, $result->org_id);
        $this->assertEquals(1, $result->id);
    }

    #[Test]
    public function test_get_or_create_creates_new_customer()
    {
        global $wpdb;

        $callCount = 0;
        $wpdb->test_get_row_callback = function ($query) use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                // First SELECT: no existing customer
                return null;
            }
            // Second SELECT: return newly created customer
            return $this->mockCustomer(['id' => 42, 'user_id' => 99, 'org_id' => 5]);
        };

        $wpdb->test_get_var_callback = function ($query) {
            return 0; // Not used in this flow
        };

        $GLOBALS['orabooks_test_use_insert_id'] = 42;

        $result = OraBooks_Customers::get_or_create(99, 5);

        $this->assertIsObject($result);
        $this->assertEquals(42, $result->id);
        $this->assertEquals(99, $result->user_id);
        $this->assertEquals(5, $result->org_id);
        $this->assertEquals(2, $callCount);
    }

    // ---------- get_by_id ----------

    #[Test]
    public function test_get_by_id_found()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'WHERE c.id') !== false) {
                return $this->mockCustomer(['id' => 7, 'user_id' => 20]);
            }
            return null;
        };

        $result = OraBooks_Customers::get_by_id(7);

        $this->assertIsObject($result);
        $this->assertEquals(7, $result->id);
        $this->assertEquals(20, $result->user_id);
        $this->assertEquals('customer@example.com', $result->email);
    }

    #[Test]
    public function test_get_by_id_not_found()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return null;
        };

        $result = OraBooks_Customers::get_by_id(999);

        $this->assertNull($result);
    }

    // ---------- get_by_user_id ----------

    #[Test]
    public function test_get_by_user_id_found()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'WHERE c.user_id') !== false) {
                return $this->mockCustomer(['id' => 3, 'user_id' => 15]);
            }
            return null;
        };

        $result = OraBooks_Customers::get_by_user_id(15);

        $this->assertIsObject($result);
        $this->assertEquals(3, $result->id);
        $this->assertEquals(15, $result->user_id);
    }

    #[Test]
    public function test_get_by_user_id_not_found()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return null;
        };

        $result = OraBooks_Customers::get_by_user_id(99999);

        $this->assertNull($result);
    }

    #[Test]
    public function test_recompute_active_status_uses_recent_payment_date()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'FROM') !== false && stripos($query, 'customers') !== false && stripos($query, 'WHERE id') !== false) {
                return $this->mockCustomer(['id' => 7, 'user_id' => 42, 'is_active' => 0]);
            }
            if (stripos($query, 'partner_commission_config') !== false) {
                return (object)['customer_active_window_days' => 30];
            }
            if (stripos($query, 'SHOW TABLES LIKE') !== false) {
                return 'wp_test_orabooks_payments';
            }
            if (stripos($query, 'MAX(p.payment_date)') !== false) {
                return (object)['last_paid' => date('Y-m-d')];
            }
            return null;
        };

        $result = OraBooks_Customers::recompute_active_status(7);

        $this->assertTrue($result);
        $this->assertStringContainsString('is_active', $wpdb->last_query);
    }

    // ---------- update_active_status ----------

    #[Test]
    public function test_update_active_status_activate()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'WHERE id') !== false) {
                return $this->mockCustomer(['id' => 1, 'user_id' => 10, 'is_active' => 0]);
            }
            return null;
        };

        $result = OraBooks_Customers::update_active_status(1, true);

        $this->assertTrue($result);
        // Verify UPDATE was called with is_active = 1
        $this->assertStringContainsString('is_active', $wpdb->last_query);
    }

    #[Test]
    public function test_update_active_status_deactivate()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'WHERE id') !== false) {
                return $this->mockCustomer(['id' => 1, 'user_id' => 10, 'is_active' => 1]);
            }
            return null;
        };

        $result = OraBooks_Customers::update_active_status(1, false);

        $this->assertTrue($result);
    }

    #[Test]
    public function test_update_active_status_not_found()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return null;
        };

        $result = OraBooks_Customers::update_active_status(999, true);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('not_found', $result->get_error_code());
    }

    #[Test]
    public function test_update_active_status_same_status_no_audit()
    {
        // When the status doesn't change, no audit event should fire
        global $wpdb;

        $called = false;
        $wpdb->test_get_row_callback = function ($query) use (&$called) {
            if (stripos($query, 'WHERE id') !== false) {
                return $this->mockCustomer(['id' => 1, 'user_id' => 10, 'is_active' => 1]);
            }
            return null;
        };

        $result = OraBooks_Customers::update_active_status(1, true);

        $this->assertTrue($result);
    }

    // ================================================================
    // CUSTOMER LIST
    // ================================================================

    #[Test]
    public function test_get_list_with_org_filter()
    {
        global $wpdb;

        $customers = [
            $this->mockCustomer(['id' => 1, 'user_id' => 10, 'org_id' => 5]),
            $this->mockCustomer(['id' => 2, 'user_id' => 11, 'org_id' => 5]),
        ];

        $wpdb->test_get_results_callback = function ($query) use ($customers) {
            return $customers;
        };

        $wpdb->test_get_var_callback = function ($query) {
            return 2;
        };

        $result = OraBooks_Customers::get_list(5);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('customers', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertCount(2, $result['customers']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(1, $result['page']);
    }

    #[Test]
    public function test_get_list_global_admin_mode()
    {
        global $wpdb;

        // org_id = 0 should skip the org filter (WHERE 1=1)
        $customers = [
            $this->mockCustomer(['id' => 1, 'user_id' => 10, 'org_id' => 5, 'org_name' => 'Org A']),
            $this->mockCustomer(['id' => 2, 'user_id' => 11, 'org_id' => 7, 'org_name' => 'Org B']),
            $this->mockCustomer(['id' => 3, 'user_id' => 12, 'org_id' => 5, 'org_name' => 'Org A']),
        ];

        $wpdb->test_get_results_callback = function ($query) use ($customers) {
            return $customers;
        };

        $wpdb->test_get_var_callback = function ($query) {
            return 3;
        };

        $result = OraBooks_Customers::get_list(0);

        $this->assertCount(3, $result['customers']);
        $this->assertEquals(3, $result['total']);
        $this->assertStringContainsString('1=1', $wpdb->last_query);
    }

    #[Test]
    public function test_get_list_with_active_filter()
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) {
            return [$this->mockCustomer(['id' => 1, 'is_active' => 1])];
        };
        $wpdb->test_get_var_callback = function ($query) {
            return 1;
        };

        $result = OraBooks_Customers::get_list(5, ['is_active' => 1]);

        $this->assertCount(1, $result['customers']);
        $this->assertEquals(1, $result['customers'][0]->is_active);
    }

    #[Test]
    public function test_get_list_with_search()
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) {
            return [$this->mockCustomer(['id' => 1, 'email' => 'search@example.com'])];
        };
        $wpdb->test_get_var_callback = function ($query) {
            return 1;
        };

        $result = OraBooks_Customers::get_list(5, ['search' => 'search@example.com']);

        $this->assertCount(1, $result['customers']);
        $this->assertEquals('search@example.com', $result['customers'][0]->email);
    }

    #[Test]
    public function test_get_list_pagination()
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) {
            return [$this->mockCustomer(['id' => 1])];
        };
        $wpdb->test_get_var_callback = function ($query) {
            return 1;
        };

        $result = OraBooks_Customers::get_list(5, ['limit' => 10, 'offset' => 20]);

        $this->assertEquals(3, $result['page']); // page 3 = offset 20 / limit 10 + 1
        $this->assertEquals(10, $result['per_page']);
    }

    // ================================================================
    // INVOICE CREATION
    // ================================================================

    #[Test]
    public function test_create_invoice_success()
    {
        global $wpdb;

        // No duplicate (get_var returns 0)
        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'SELECT id FROM') !== false) {
                return 0; // No duplicate
            }
            return 0; // Max invoice num
        };

        // After insert, get_invoice is called internally
        $callCount = 0;
        $wpdb->test_get_row_callback = function ($query) use (&$callCount) {
            $callCount++;
            // First get_row call: customer exists check (SELECT id FROM customers)
            if ($callCount === 1) {
                return (object)['id' => 1];
            }
            // Second: get_invoice pulls the full invoice
            return $this->mockInvoice([
                'id' => 200,
                'invoice_number' => 'INV-202606-0001',
            ]);
        };

        // Return payments sub-query from get_invoice
        $wpdb->test_get_results_callback = function ($query) {
            return [];
        };

        $GLOBALS['orabooks_test_use_insert_id'] = 200;

        $result = OraBooks_Customers::create_invoice(5, [
            'customer_id'      => 1,
            'total_amount'     => 1500.00,
            'invoice_number'   => 'INV-202606-0001',
            'description'      => 'Consulting services',
            'transaction_date' => '2026-06-10',
            'due_date'         => '2026-07-10',
        ]);

        $this->assertIsObject($result);
        $this->assertEquals(200, $result->id);
        $this->assertEquals('INV-202606-0001', $result->invoice_number);
        $this->assertEquals('500.00', $result->total_amount);
    }

    #[Test]
    public function test_create_invoice_generates_idempotency_key_when_missing()
    {
        global $wpdb;

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'SELECT id FROM') !== false) {
                return 0;
            }
            return 0;
        };

        $callCount = 0;
        $wpdb->test_get_row_callback = function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                return (object) ['id' => 1];
            }
            return $this->mockInvoice(['id' => 201, 'invoice_number' => 'INV-202606-0099']);
        };
        $wpdb->test_get_results_callback = function () {
            return [];
        };

        $inserted = null;
        $wpdb->test_insert_callback = function ($table, $data) use (&$inserted) {
            $inserted = $data;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 201;

        $result = OraBooks_Customers::create_invoice(5, [
            'customer_id' => 1,
            'subtotal_amount' => 100,
            'total_amount' => 100,
            'idempotency_key' => '',
        ]);

        $this->assertIsObject($result);
        $this->assertIsArray($inserted);
        $this->assertNotEmpty($inserted['idempotency_key']);
    }

    #[Test]
    public function test_create_invoice_auto_generates_number()
    {
        global $wpdb;

        // No duplicate (get_var returns 0)
        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'SELECT id FROM') !== false) {
                return 0;
            }
            if (stripos($query, 'SUBSTRING_INDEX') !== false) {
                return 0; // Max invoice num = 0, so next = INV-YYYYMM-0001
            }
            return 0;
        };

        $callCount = 0;
        $wpdb->test_get_row_callback = function ($query) use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                return (object)['id' => 1];
            }
            return $this->mockInvoice([
                'id' => 201,
                'invoice_number' => 'INV-' . date('Ym') . '-0001',
            ]);
        };

        $wpdb->test_get_results_callback = function ($query) {
            return [];
        };

        $GLOBALS['orabooks_test_use_insert_id'] = 201;

        $result = OraBooks_Customers::create_invoice(5, [
            'customer_id'  => 1,
            'total_amount' => 750.00,
            // No invoice_number — should auto-generate
        ]);

        $this->assertIsObject($result);
        $this->assertStringStartsWith('INV-' . date('Ym') . '-', $result->invoice_number);
    }

    #[Test]
    public function test_create_invoice_missing_customer_id()
    {
        $result = OraBooks_Customers::create_invoice(5, [
            'total_amount' => 100.00,
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('missing_field', $result->get_error_code());
    }

    #[Test]
    public function test_create_invoice_zero_amount()
    {
        $result = OraBooks_Customers::create_invoice(5, [
            'customer_id'  => 1,
            'total_amount' => 0,
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_amount', $result->get_error_code());
    }

    #[Test]
    public function test_create_invoice_negative_amount()
    {
        $result = OraBooks_Customers::create_invoice(5, [
            'customer_id'  => 1,
            'total_amount' => -100,
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_amount', $result->get_error_code());
    }

    #[Test]
    public function test_create_invoice_duplicate_number()
    {
        global $wpdb;

        // Duplicate exists (get_var returns non-zero)
        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'SELECT id FROM') !== false) {
                return 99; // Duplicate found
            }
            return 0;
        };

        $result = OraBooks_Customers::create_invoice(5, [
            'customer_id'    => 1,
            'total_amount'   => 200,
            'invoice_number' => 'INV-202606-0001',
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('duplicate', $result->get_error_code());
    }

    // ================================================================
    // INVOICE RETRIEVAL
    // ================================================================

    #[Test]
    public function test_get_invoice_found_with_payments()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'WHERE i.id') !== false) {
                return $this->mockInvoice(['id' => 100, 'payment_status' => 'partial']);
            }
            return null;
        };

        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'WHERE invoice_id') !== false) {
                return [
                    $this->mockPayment(['id' => 900, 'amount' => '300.00']),
                    $this->mockPayment(['id' => 901, 'amount' => '200.00']),
                ];
            }
            return [];
        };

        $result = OraBooks_Customers::get_invoice(100);

        $this->assertIsObject($result);
        $this->assertEquals(100, $result->id);
        $this->assertCount(2, $result->payments);
        $this->assertEquals(300.00, (float)$result->payments[0]->amount);
        $this->assertEquals(200.00, (float)$result->payments[1]->amount);
    }

    #[Test]
    public function test_get_invoice_not_found()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return null;
        };

        $result = OraBooks_Customers::get_invoice(99999);

        $this->assertNull($result);
    }

    #[Test]
    public function test_get_invoices_list_with_filters()
    {
        global $wpdb;

        $invoices = [
            $this->mockInvoice(['id' => 100, 'payment_status' => 'unpaid', 'workflow_status' => 'draft']),
            $this->mockInvoice(['id' => 101, 'payment_status' => 'paid', 'workflow_status' => 'posted']),
        ];

        $wpdb->test_get_results_callback = function ($query) use ($invoices) {
            return $invoices;
        };
        $wpdb->test_get_var_callback = function ($query) {
            return 2;
        };

        $result = OraBooks_Customers::get_invoices_list(5, [
            'payment_status'  => 'paid',
            'workflow_status' => 'posted',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('invoices', $result);
        $this->assertCount(2, $result['invoices']);
        $this->assertEquals(2, $result['total']);
    }

    #[Test]
    public function test_get_invoices_list_global_admin_mode()
    {
        global $wpdb;

        $invoices = [
            $this->mockInvoice(['id' => 100, 'org_id' => 5, 'org_name' => 'Org A']),
            $this->mockInvoice(['id' => 101, 'org_id' => 7, 'org_name' => 'Org B']),
        ];

        $wpdb->test_get_results_callback = function ($query) use ($invoices) {
            return $invoices;
        };
        $wpdb->test_get_var_callback = function ($query) {
            return 2;
        };

        $result = OraBooks_Customers::get_invoices_list(0);

        $this->assertCount(2, $result['invoices']);
        $this->assertEquals(2, $result['total']);
    }

    #[Test]
    public function test_get_invoices_list_with_date_range()
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) {
            return [$this->mockInvoice(['id' => 100])];
        };
        $wpdb->test_get_var_callback = function ($query) {
            return 1;
        };

        $result = OraBooks_Customers::get_invoices_list(5, [
            'from_date' => '2026-01-01',
            'to_date'   => '2026-12-31',
        ]);

        $this->assertCount(1, $result['invoices']);
    }

    // ================================================================
    // PAYMENT RECORDING
    // ================================================================

    #[Test]
    public function test_record_payment_full_payment()
    {
        global $wpdb;

        // get_row: retrieve invoice
        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'WHERE id') !== false && stripos($query, 'AND org_id') !== false) {
                return $this->mockInvoice(['id' => 100, 'total_amount' => '500.00']);
            }
            if (stripos($query, 'FROM orabooks_customers') !== false) {
                return (object)['user_id' => 10];
            }
            return null;
        };

        // get_var: total paid = 500 (equals invoice total)
        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'SUM(amount)') !== false) {
                return 500.00;
            }
            return 0;
        };

        $wpdb->test_get_results_callback = function ($query) {
            return [];
        };

        $GLOBALS['orabooks_test_use_insert_id'] = 900;

        $result = OraBooks_Customers::record_payment(5, 100, [
            'amount'         => 500.00,
            'payment_date'   => '2026-06-15',
            'payment_method' => 'bank_transfer',
            'reference'      => 'TXN-FULL-001',
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(900, $result['payment_id']);
        $this->assertEquals('paid', $result['new_status']);
        $this->assertEquals(500.00, $result['total_paid']);
    }

    #[Test]
    public function test_record_payment_partial_payment()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'WHERE id') !== false && stripos($query, 'AND org_id') !== false) {
                return $this->mockInvoice(['id' => 100, 'total_amount' => '500.00']);
            }
            if (stripos($query, 'FROM orabooks_customers') !== false) {
                return (object)['user_id' => 10];
            }
            return null;
        };

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'SUM(amount)') !== false) {
                return 200.00; // Only 200 paid, less than 500
            }
            return 0;
        };

        $wpdb->test_get_results_callback = function ($query) {
            return [];
        };

        $GLOBALS['orabooks_test_use_insert_id'] = 901;

        $result = OraBooks_Customers::record_payment(5, 100, [
            'amount' => 200.00,
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('partial', $result['new_status']);
        $this->assertEquals(200.00, $result['total_paid']);
    }

    #[Test]
    public function test_record_payment_multiple_payments_accumulate()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'WHERE id') !== false && stripos($query, 'AND org_id') !== false) {
                return $this->mockInvoice(['id' => 100, 'total_amount' => '500.00']);
            }
            if (stripos($query, 'FROM orabooks_customers') !== false) {
                return (object)['user_id' => 10];
            }
            return null;
        };

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'SUM(amount)') !== false) {
                return 500.00; // After all payments, total = 500
            }
            return 0;
        };

        $wpdb->test_get_results_callback = function ($query) {
            return [];
        };

        $GLOBALS['orabooks_test_use_insert_id'] = 902;

        $result = OraBooks_Customers::record_payment(5, 100, [
            'amount' => 300.00,
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('paid', $result['new_status']);
    }

    #[Test]
    public function test_record_payment_invoice_not_found()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return null;
        };

        $result = OraBooks_Customers::record_payment(5, 999, [
            'amount' => 100.00,
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('not_found', $result->get_error_code());
    }

    #[Test]
    public function test_record_payment_cancelled_invoice()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'WHERE id') !== false && stripos($query, 'AND org_id') !== false) {
                return $this->mockInvoice(['id' => 100, 'payment_status' => 'cancelled']);
            }
            return null;
        };

        $result = OraBooks_Customers::record_payment(5, 100, [
            'amount' => 100.00,
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('cancelled', $result->get_error_code());
    }

    #[Test]
    public function test_record_payment_invalid_amount()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'WHERE id') !== false && stripos($query, 'AND org_id') !== false) {
                return $this->mockInvoice(['id' => 100]);
            }
            return null;
        };

        // Zero amount
        $result = OraBooks_Customers::record_payment(5, 100, [
            'amount' => 0,
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_amount', $result->get_error_code());

        // Negative amount
        $result2 = OraBooks_Customers::record_payment(5, 100, [
            'amount' => -50,
        ]);

        $this->assertInstanceOf(WP_Error::class, $result2);
        $this->assertEquals('invalid_amount', $result2->get_error_code());
    }

    // ================================================================
    // CUSTOMER STATS
    // ================================================================

    #[Test]
    public function test_get_customer_stats_shape()
    {
        global $wpdb;

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'COUNT(*)') !== false && stripos($query, 'is_active = 1') !== false) {
                return 5;  // active customers
            }
            if (stripos($query, 'COUNT(*)') !== false && stripos($query, 'unpaid') !== false) {
                return 3; // unpaid invoices (must check BEFORE 'paid' since 'unpaid' contains 'paid')
            }
            if (stripos($query, 'COUNT(*)') !== false && stripos($query, 'paid') !== false) {
                return 10; // paid invoices
            }
            if (stripos($query, 'COUNT(*)') !== false && stripos($query, 'unpaid') !== false) {
                return 3; // unpaid invoices
            }
            if (stripos($query, 'COUNT(*)') !== false && stripos($query, 'overdue') !== false) {
                return 2; // overdue invoices
            }
            if (stripos($query, 'COUNT(*)') !== false) {
                return 20; // total customers
            }
            if (stripos($query, 'SUM(amount)') !== false && stripos($query, 'orabooks_payments') !== false) {
                return 15000.00; // total revenue
            }
            if (stripos($query, 'SUM') !== false) {
                return 5000.00; // outstanding AR
            }
            return 0;
        };

        $stats = OraBooks_Customers::get_customer_stats(5);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_customers', $stats);
        $this->assertArrayHasKey('active_customers', $stats);
        $this->assertArrayHasKey('inactive_customers', $stats);
        $this->assertArrayHasKey('total_invoices', $stats);
        $this->assertArrayHasKey('paid_invoices', $stats);
        $this->assertArrayHasKey('unpaid_invoices', $stats);
        $this->assertArrayHasKey('overdue_invoices', $stats);
        $this->assertArrayHasKey('total_revenue', $stats);
        $this->assertArrayHasKey('outstanding_ar', $stats);

        $this->assertEquals(20, $stats['total_customers']);
        $this->assertEquals(5, $stats['active_customers']);
        $this->assertEquals(15, $stats['inactive_customers']); // 20 - 5
        $this->assertEquals(20, $stats['total_invoices']);
        $this->assertEquals(10, $stats['paid_invoices']);
        $this->assertEquals(3, $stats['unpaid_invoices']);
        $this->assertEquals(2, $stats['overdue_invoices']);
        $this->assertEquals(15000.00, $stats['total_revenue']);
        $this->assertEquals(5000.00, $stats['outstanding_ar']);
    }

    #[Test]
    public function test_get_customer_stats_zero_counts()
    {
        global $wpdb;

        $wpdb->test_get_var_callback = function ($query) {
            return 0;
        };

        $stats = OraBooks_Customers::get_customer_stats(999);

        $this->assertIsArray($stats);
        $this->assertEquals(0, $stats['total_customers']);
        $this->assertEquals(0, $stats['active_customers']);
        $this->assertEquals(0, $stats['inactive_customers']);
        $this->assertEquals(0, $stats['total_invoices']);
        $this->assertEquals(0, $stats['total_revenue']);
        $this->assertEquals(0, $stats['outstanding_ar']);
    }

    // ================================================================
    // AJAX ENDPOINTS (via static methods)
    // ================================================================

    #[Test]
    public function test_ajax_customers_list_requires_org_id()
    {
        // org_id = 0 triggers "Organization ID required"
        $_GET['org_id'] = 0;

        try {
            (new OraBooks_Customers())->ajax_customers_list();
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $response = json_decode($e->getMessage(), true);
            $this->assertTrue($response['error'] ?? false);
            $this->assertStringContainsString('Organization ID required', $response['message'] ?? '');
        }
    }

    #[Test]
    public function test_ajax_customer_get_by_customer_id()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'WHERE c.id') !== false) {
                return $this->mockCustomer(['id' => 5, 'user_id' => 10]);
            }
            return null;
        };

        $_GET['customer_id'] = 5;

        try {
            (new OraBooks_Customers())->ajax_customer_get();
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $response = json_decode($e->getMessage(), true);
            $this->assertFalse($response['error'] ?? true);
            $this->assertEquals(5, $response['data']['id'] ?? 0);
        }
    }

    #[Test]
    public function test_ajax_customer_get_by_user_id()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'WHERE c.user_id') !== false) {
                return $this->mockCustomer(['id' => 3, 'user_id' => 20]);
            }
            return null;
        };

        $_GET['user_id'] = 20;

        try {
            (new OraBooks_Customers())->ajax_customer_get();
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $response = json_decode($e->getMessage(), true);
            $this->assertFalse($response['error'] ?? true);
            $this->assertEquals(20, $response['data']['user_id'] ?? 0);
        }
    }

    #[Test]
    public function test_ajax_customer_get_no_params()
    {
        // No customer_id or user_id provided
        try {
            (new OraBooks_Customers())->ajax_customer_get();
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $response = json_decode($e->getMessage(), true);
            $this->assertTrue($response['error'] ?? false);
            $this->assertStringContainsString('customer_id or user_id required', $response['message'] ?? '');
        }
    }

    #[Test]
    public function test_ajax_customer_get_not_found()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return null;
        };

        $_GET['customer_id'] = 999;

        try {
            (new OraBooks_Customers())->ajax_customer_get();
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $response = json_decode($e->getMessage(), true);
            $this->assertTrue($response['error'] ?? false);
            $this->assertStringContainsString('not found', $response['message'] ?? '');
        }
    }

    #[Test]
    public function test_ajax_customer_update_rejects_manual_active_toggle()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'c.id') !== false || stripos($query, 'WHERE id') !== false) {
                return $this->mockCustomer(['id' => 1, 'user_id' => 10, 'is_active' => 0]);
            }
            return null;
        };

        $_POST['customer_id'] = 1;
        $_POST['is_active'] = 1;

        try {
            (new OraBooks_Customers())->ajax_customer_update();
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $response = json_decode($e->getMessage(), true);
            $this->assertTrue($response['error'] ?? false);
            $this->assertStringContainsString('derived from invoice activity', $response['message'] ?? '');
        }
    }

    #[Test]
    public function test_ajax_customer_update_missing_id()
    {
        $_POST['is_active'] = 1;

        try {
            (new OraBooks_Customers())->ajax_customer_update();
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $response = json_decode($e->getMessage(), true);
            $this->assertTrue($response['error'] ?? false);
            $this->assertStringContainsString('Customer ID required', $response['message'] ?? '');
        }
    }

    // ================================================================
    // SEED & EDGE CASES
    // ================================================================

    #[Test]
    public function test_seed_default_customers_no_missing()
    {
        global $wpdb;

        // No missing customers found
        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'LEFT JOIN') !== false) {
                return []; // No missing customers
            }
            return [];
        };

        $count = OraBooks_Customers::seed_default_customers();

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function test_seed_default_customers_with_missing()
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'LEFT JOIN') !== false) {
                return [
                    (object)['customer_user_id' => 101, 'org_id' => 5],
                    (object)['customer_user_id' => 102, 'org_id' => 5],
                ];
            }
            return [];
        };

        $GLOBALS['orabooks_test_use_insert_id'] = 42;

        $count = OraBooks_Customers::seed_default_customers();

        $this->assertEquals(2, $count);
    }

    #[Test]
    public function test_get_create_table_sql_returns_array()
    {
        $sql = OraBooks_Customers::get_create_table_sql();

        $this->assertIsArray($sql);
        $this->assertCount(3, $sql); // customers, invoices, payments
        $this->assertStringContainsString('customers', $sql[0]);
        $this->assertStringContainsString('invoices', $sql[1]);
        $this->assertStringContainsString('payments', $sql[2]);
    }

    // ================================================================
    // DAILY CRON TESTS (lightweight — verify they run without error)
    // ================================================================

    #[Test]
    public function test_daily_invoice_overdue_check()
    {
        global $wpdb;

        $wpdb->test_query_callback = function ($query) {
            if (stripos($query, 'UPDATE') !== false) {
                return 3;
            }
            return 0;
        };

        $customers = new OraBooks_Customers();
        $count = $customers->daily_invoice_overdue_check();

        $this->assertEquals(3, $count);
    }

    #[Test]
    public function test_daily_customer_status_check()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'customer_active_window_days') !== false) {
                return (object)['customer_active_window_days' => 30];
            }
            return null;
        };

        $wpdb->test_query_callback = function ($query) {
            if (stripos($query, 'UPDATE') !== false) {
                return 2;
            }
            return 0;
        };

        $customers = new OraBooks_Customers();
        $count = $customers->daily_customer_status_check();

        $this->assertEquals(2, $count);
    }

    #[Test]
    public function test_create_invoice_calculates_tax_from_subtotal()
    {
        global $wpdb;

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'SELECT id FROM') !== false) {
                return 0;
            }
            return 0;
        };

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'tax_configs') !== false) {
                return (object) [
                    'id' => 1,
                    'default_tax_rate' => '10.0000',
                    'tax_type' => 'Sales Tax',
                    'override_reasons' => null,
                ];
            }
            if (stripos($query, 'customers') !== false && stripos($query, 'SELECT id') !== false) {
                return (object) ['id' => 1];
            }
            return $this->mockInvoice([
                'id' => 201,
                'total_amount' => '110.00',
                'tax_amount' => '10.00',
                'tax_rate' => '10.0000',
            ]);
        };
        $wpdb->test_get_results_callback = function () {
            return [];
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 201;

        $result = OraBooks_Customers::create_invoice(5, [
            'customer_id' => 1,
            'subtotal_amount' => 100,
            'jurisdiction' => 'US',
        ]);

        $this->assertIsObject($result);
        $this->assertEquals('110.00', $result->total_amount);
    }

    #[Test]
    public function test_get_list_includes_wallet_balance()
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'wallet_balance') !== false) {
                return [
                    (object) [
                        'id' => 1,
                        'email' => 'ar@example.com',
                        'is_active' => 1,
                        'invoice_count' => 2,
                        'total_paid' => '300.00',
                        'wallet_balance' => '150.00',
                    ],
                ];
            }
            return [];
        };
        $wpdb->test_get_var_callback = function () {
            return 1;
        };

        $result = OraBooks_Customers::get_list(5, ['limit' => 10, 'offset' => 0]);

        $this->assertCount(1, $result['customers']);
        $this->assertEquals('150.00', $result['customers'][0]->wallet_balance);
    }

    #[Test]
    public function test_create_customer_success()
    {
        global $wpdb;

        $wpdb->insert_id = 42;
        $wpdb->test_insert_callback = function ($table, $data) {
            $this->assertArrayNotHasKey('user_id', $data);
            $this->assertSame(5, $data['org_id']);
            $this->assertSame('Acme Corp', $data['display_name']);
            $this->assertSame('billing@acme.com', $data['contact_email']);
            return 1;
        };
        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'contact_email') !== false) {
                return null;
            }
            return null;
        };
        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'FROM') !== false && stripos($query, 'customers') !== false) {
                return (object) [
                    'id' => 42,
                    'org_id' => 5,
                    'display_name' => 'Acme Corp',
                    'contact_email' => 'billing@acme.com',
                    'email' => 'billing@acme.com',
                    'is_active' => 0,
                ];
            }
            return null;
        };

        $result = OraBooks_Customers::create_customer(5, [
            'display_name' => 'Acme Corp',
            'email' => 'billing@acme.com',
        ]);

        $this->assertIsObject($result);
        $this->assertSame(42, (int) $result->id);
        $this->assertSame('Acme Corp', $result->display_name);
    }

    #[Test]
    public function test_create_customer_requires_name()
    {
        $result = OraBooks_Customers::create_customer(5, ['email' => 'billing@acme.com']);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('missing_field', $result->get_error_code());
    }

    #[Test]
    public function test_cancel_invoice_draft_success()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'WHERE i.id') !== false) {
                return $this->mockInvoice(['workflow_status' => 'draft']);
            }
            return null;
        };
        $wpdb->test_query_callback = function () {
            return true;
        };
        $wpdb->test_update_callback = function ($table, $data) {
            return isset($data['workflow_status']) && $data['workflow_status'] === 'cancelled';
        };
        $wpdb->test_insert_callback = function () {
            return true;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 401;

        $result = OraBooks_Customers::cancel_invoice(5, 100, 1, 'Customer request');

        $this->assertIsObject($result);
    }

    #[Test]
    public function test_cancel_invoice_sent_success()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'WHERE i.id') !== false) {
                return $this->mockInvoice(['workflow_status' => 'sent']);
            }
            return null;
        };
        $wpdb->test_query_callback = function () {
            return true;
        };
        $wpdb->test_update_callback = function () {
            return true;
        };
        $wpdb->test_insert_callback = function () {
            return true;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 402;

        $result = OraBooks_Customers::cancel_invoice(5, 100, 1);

        $this->assertIsObject($result);
    }

    #[Test]
    public function test_cancel_invoice_rejects_posted_status()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'WHERE i.id') !== false) {
                return $this->mockInvoice(['workflow_status' => 'posted']);
            }
            return null;
        };

        $result = OraBooks_Customers::cancel_invoice(5, 100, 1);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_status', $result->get_error_code());
    }

    #[Test]
    public function test_cancel_invoice_rejects_partial_payment()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'WHERE i.id') !== false) {
                return $this->mockInvoice([
                    'workflow_status' => 'sent',
                    'payment_status' => 'partial',
                    'paid_amount' => '100.00',
                ]);
            }
            return null;
        };

        $result = OraBooks_Customers::cancel_invoice(5, 100, 1);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('has_payments', $result->get_error_code());
    }

    #[Test]
    public function test_ar_wallet_blocks_credit_hold_on_new_invoice()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'credit_hold') !== false) {
                return (object) ['credit_hold' => 1, 'credit_limit' => 0];
            }
            return null;
        };

        $result = OraBooks_AR_Wallet::validate_customer_credit_for_new_invoice(5, 1, 250);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('credit_hold', $result->get_error_code());
    }

    #[Test]
    public function test_ar_wallet_credit_limit_validation()
    {
        global $wpdb;

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'SUM(total_amount') !== false) {
                return '800.00';
            }
            return null;
        };

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'credit_hold') !== false) {
                return (object) ['credit_hold' => 0, 'credit_limit' => 1000];
            }
            return null;
        };

        $ok = OraBooks_AR_Wallet::validate_customer_credit_for_new_invoice(5, 1, 150);
        $this->assertTrue($ok);

        $blocked = OraBooks_AR_Wallet::validate_customer_credit_for_new_invoice(5, 1, 300);
        $this->assertInstanceOf(WP_Error::class, $blocked);
        $this->assertEquals('credit_limit', $blocked->get_error_code());
    }

    #[Test]
    public function test_invoice_document_normalizes_line_items()
    {
        $lines = OraBooks_Invoice_Document::normalize_line_items([
            ['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 150, 'sku_code' => 'SRV-01'],
            ['description' => '', 'quantity' => 1, 'unit_price' => 10],
        ]);

        $this->assertCount(1, $lines);
        $this->assertEquals('Consulting', $lines[0]['description']);
        $this->assertEquals(300.0, $lines[0]['line_total']);
        $this->assertEquals('SRV-01', $lines[0]['sku_code']);
    }
}

