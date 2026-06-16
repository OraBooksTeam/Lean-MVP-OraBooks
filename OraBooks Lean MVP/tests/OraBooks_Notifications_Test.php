<?php
/**
 * Unit Tests for OraBooks_Notifications — Invoice Event Handlers (SL-021 Integration)
 *
 * Covers: on_invoice_created, on_payment_recorded, on_invoices_marked_overdue
 *
 * Run: phpunit --configuration tests/phpunit.xml --testsuite="OraBooks SL-250 Notification Tests"
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Notifications_Test extends TestCase
{
    // ================================================================
    // Lifecycle
    // ================================================================

    protected function setUp(): void
    {
        parent::setUp();

        // Reset all test globals to defaults
        $GLOBALS['orabooks_test_current_user_id'] = 1;
        $GLOBALS['orabooks_test_current_user_can'] = true;

        // Reset superglobals
        $_POST = [];
        $_GET  = [];

        // Reset wpdb callbacks and state
        global $wpdb;
        $wpdb->test_get_var_callback     = null;
        $wpdb->test_get_row_callback     = null;
        $wpdb->test_get_results_callback = null;
        $wpdb->test_query_callback       = null;
        $wpdb->insert_id = 0;
        $wpdb->last_query = '';
        $wpdb->last_result = [];

        // Reset user meta
        $GLOBALS['orabooks_test_user_meta'] = [];
    }

    // ================================================================
    // Helpers
    // ================================================================

    /**
     * Pre-populate user notification preferences so that
     * resolve_channels() can decode channels without error.
     *
     * send_notification() calls get_user_preferences() which stores
     * channels as a PHP array by default. resolve_channels() then
     * calls json_decode($user_prefs->channels) which fails on an array.
     * We pre-set user_meta with channels stored as a JSON string.
     */
    private function setUserNotifPrefs(int $user_id): void
    {
        update_user_meta($user_id, 'orabooks_notification_prefs', [
            'channels'           => json_encode(['email', 'inapp']),
            'quiet_hours_start'  => '',
            'quiet_hours_end'    => '',
            'digest'             => 'none',
            'language'           => 'en',
            'escalation_enabled' => true,
            'updated_at'         => current_time('mysql', true),
        ]);
    }

    /**
     * Create a mock notifications instance.
     */
    private function createHandler(): OraBooks_Notifications
    {
        return OraBooks_Notifications::init();
    }

    // ================================================================
    // on_invoice_created
    // ================================================================

    #[Test]
    public function test_on_invoice_created_sends_notification()
    {
        global $wpdb;

        $this->setUserNotifPrefs(42);

        $wpdb->test_get_row_callback = function ($query) {
            // Customer lookup (get_by_id uses WHERE c.id = %d)
            if (stripos($query, 'WHERE c.id') !== false) {
                return (object) [
                    'id'      => 5,
                    'user_id' => 42,
                    'org_id'  => 10,
                    'email'   => 'customer@example.com',
                ];
            }
            // Org policy lookup
            if (stripos($query, 'org_notification_policies') !== false) {
                return null;
            }
            // User org lookup
            if (stripos($query, 'orabooks_users') !== false) {
                return (object) ['org_id' => 10];
            }
            return null;
        };

        // Dedup check — no existing entry
        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'notification_dedup_log') !== false) {
                return 0;
            }
            if (stripos($query, 'orabooks_notifications') !== false) {
                return 0;
            }
            return 0;
        };

        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'delivery_provider_health') !== false) {
                return [];
            }
            return [];
        };

        $GLOBALS['orabooks_test_use_insert_id'] = 5001;

        $handler = $this->createHandler();
        $handler->on_invoice_created(100, [
            'customer_id'    => 5,
            'invoice_number' => 'INV-001',
            'total_amount'   => 1500.00,
            'due_date'       => '2026-07-15',
            'org_id'         => 10,
        ]);

        $this->assertGreaterThan(0, $wpdb->insert_id, 'Notification insert ID should be set');
    }

    #[Test]
    public function test_on_invoice_created_missing_customer_id()
    {
        global $wpdb;

        $handler = $this->createHandler();
        $handler->on_invoice_created(100, [
            'invoice_number' => 'INV-001',
            'total_amount'   => 1500.00,
            'org_id'         => 10,
        ]);

        $this->assertEquals(0, $wpdb->insert_id, 'No notification when customer_id is missing');
    }

    #[Test]
    public function test_on_invoice_created_missing_org_id()
    {
        global $wpdb;

        $handler = $this->createHandler();
        $handler->on_invoice_created(100, [
            'customer_id'    => 5,
            'invoice_number' => 'INV-001',
            'total_amount'   => 1500.00,
        ]);

        $this->assertEquals(0, $wpdb->insert_id, 'No notification when org_id is missing');
    }

    #[Test]
    public function test_on_invoice_created_customer_not_found()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return null;
        };

        $handler = $this->createHandler();
        $handler->on_invoice_created(100, [
            'customer_id'    => 999,
            'invoice_number' => 'INV-001',
            'total_amount'   => 1500.00,
            'due_date'       => '2026-07-15',
            'org_id'         => 10,
        ]);

        $this->assertEquals(0, $wpdb->insert_id, 'No notification when customer not found');
    }

    // ================================================================
    // on_payment_recorded
    // ================================================================

    #[Test]
    public function test_on_payment_recorded_paid_in_full()
    {
        global $wpdb;

        $this->setUserNotifPrefs(42);

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'org_notification_policies') !== false) {
                return null;
            }
            if (stripos($query, 'orabooks_users') !== false) {
                return (object) ['org_id' => 10];
            }
            return null;
        };

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'notification_dedup_log') !== false) {
                return 0;
            }
            return 0;
        };

        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'delivery_provider_health') !== false) {
                return [];
            }
            return [];
        };

        $GLOBALS['orabooks_test_use_insert_id'] = 5002;

        $handler = $this->createHandler();
        $handler->on_payment_recorded(900, [
            'customer_user_id' => 42,
            'invoice_number'   => 'INV-001',
            'amount'           => 500.00,
            'new_status'       => 'paid',
            'payment_date'     => '2026-06-15',
            'org_id'           => 10,
        ]);

        $this->assertGreaterThan(0, $wpdb->insert_id, 'Notification should be created for paid in full');
    }

    #[Test]
    public function test_on_payment_recorded_partial_payment()
    {
        global $wpdb;

        $this->setUserNotifPrefs(42);

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'org_notification_policies') !== false) {
                return null;
            }
            if (stripos($query, 'orabooks_users') !== false) {
                return (object) ['org_id' => 10];
            }
            return null;
        };

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'notification_dedup_log') !== false) {
                return 0;
            }
            return 0;
        };

        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'delivery_provider_health') !== false) {
                return [];
            }
            return [];
        };

        $GLOBALS['orabooks_test_use_insert_id'] = 5003;

        $handler = $this->createHandler();
        $handler->on_payment_recorded(901, [
            'customer_user_id' => 42,
            'invoice_number'   => 'INV-002',
            'amount'           => 200.00,
            'new_status'       => 'partial',
            'payment_date'     => '2026-06-15',
            'org_id'           => 10,
        ]);

        $this->assertGreaterThan(0, $wpdb->insert_id, 'Notification should be created for partial payment');
    }

    #[Test]
    public function test_on_payment_recorded_missing_customer_user_id()
    {
        global $wpdb;

        $handler = $this->createHandler();
        $handler->on_payment_recorded(900, [
            'invoice_number' => 'INV-001',
            'amount'         => 500.00,
            'new_status'     => 'paid',
            'org_id'         => 10,
        ]);

        $this->assertEquals(0, $wpdb->insert_id, 'No notification when customer_user_id is missing');
    }

    #[Test]
    public function test_on_payment_recorded_missing_org_id()
    {
        global $wpdb;

        $handler = $this->createHandler();
        $handler->on_payment_recorded(900, [
            'customer_user_id' => 42,
            'invoice_number'   => 'INV-001',
            'amount'           => 500.00,
            'new_status'       => 'paid',
            'payment_date'     => '2026-06-15',
        ]);

        $this->assertEquals(0, $wpdb->insert_id, 'No notification when org_id is missing');
    }

    // ================================================================
    // on_invoices_marked_overdue
    // ================================================================

    #[Test]
    public function test_on_invoices_marked_overdue_sends_notifications()
    {
        global $wpdb;

        $this->setUserNotifPrefs(42);
        $this->setUserNotifPrefs(55);

        // Return overdue invoices for the JOIN query
        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'orabooks_invoices') !== false && stripos($query, 'JOIN') !== false) {
                return [
                    (object) [
                        'id'                => 200,
                        'invoice_number'    => 'INV-001',
                        'total_amount'      => '500.00',
                        'due_date'          => '2026-06-01',
                        'org_id'            => 10,
                        'customer_user_id'  => 42,
                    ],
                    (object) [
                        'id'                => 201,
                        'invoice_number'    => 'INV-002',
                        'total_amount'      => '750.00',
                        'due_date'          => '2026-06-05',
                        'org_id'            => 10,
                        'customer_user_id'  => 55,
                    ],
                ];
            }
            if (stripos($query, 'delivery_provider_health') !== false) {
                return [];
            }
            return [];
        };

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'org_notification_policies') !== false) {
                return null;
            }
            if (stripos($query, 'orabooks_users') !== false) {
                return (object) ['org_id' => 10];
            }
            return null;
        };

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'notification_dedup_log') !== false) {
                return 0;
            }
            return 0;
        };

        $GLOBALS['orabooks_test_use_insert_id'] = 6001;

        $handler = $this->createHandler();
        $handler->on_invoices_marked_overdue(2, []);

        $this->assertGreaterThan(0, $wpdb->insert_id, 'At least one overdue notification should be created');
    }

    #[Test]
    public function test_on_invoices_marked_overdue_zero_count()
    {
        global $wpdb;

        $handler = $this->createHandler();
        $handler->on_invoices_marked_overdue(0, []);

        $this->assertEquals('', $wpdb->last_query, 'No queries should run when overdue count is 0');
    }

    #[Test]
    public function test_on_invoices_marked_overdue_no_invoices_found()
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) {
            return [];
        };
        $wpdb->test_get_var_callback = function ($query) {
            return 0;
        };

        $handler = $this->createHandler();
        $handler->on_invoices_marked_overdue(1, []);

        $this->assertEquals(0, $wpdb->insert_id, 'No notification when overdue invoices not found');
    }

    #[Test]
    public function test_on_invoices_marked_overdue_sends_single_customer_notification()
    {
        global $wpdb;

        $this->setUserNotifPrefs(42);

        // Only one overdue invoice — tests that the handler works with a single result
        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'orabooks_invoices') !== false && stripos($query, 'JOIN') !== false) {
                return [
                    (object) [
                        'id'                => 300,
                        'invoice_number'    => 'INV-003',
                        'total_amount'      => '250.00',
                        'due_date'          => '2026-06-10',
                        'org_id'            => 10,
                        'customer_user_id'  => 42,
                    ],
                ];
            }
            if (stripos($query, 'delivery_provider_health') !== false) {
                return [];
            }
            return [];
        };

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'org_notification_policies') !== false) {
                return null;
            }
            if (stripos($query, 'orabooks_users') !== false) {
                return (object) ['org_id' => 10];
            }
            return null;
        };

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'notification_dedup_log') !== false) {
                return 0;
            }
            return 0;
        };

        $GLOBALS['orabooks_test_use_insert_id'] = 7001;

        $handler = $this->createHandler();
        $handler->on_invoices_marked_overdue(1, []);

        $this->assertGreaterThan(0, $wpdb->insert_id, 'Notification should be created for single overdue invoice');
    }
}
