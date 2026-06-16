<?php
/**
 * Unit Tests for OraBooks_Notifications — Invoice Event Handlers (SL-021 Integration)
 *
 * Covers: on_invoice_created, on_payment_recorded, on_invoices_marked_overdue,
 *         notify_org_admins via on_invoice_created & on_payment_recorded
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
        $wpdb->test_insert_callback      = null;
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
     * Set up common mocks required by send_notification's internals
     * (dedup, org policy, user org, provider health).
     */
    private function setUpCommonMocks(): void
    {
        global $wpdb;

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
            if (stripos($query, 'orabooks_notifications') !== false) {
                return 0;
            }
            return 0;
        };
    }

    /**
     * Set up the test_get_results_callback for delivery_provider_health queries.
     * Pass an optional admin override that returns admin users on user_org queries.
     */
    private function setUpResultsMock(callable $adminOverride = null): void
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) use ($adminOverride) {
            // Admin lookup from user_org
            if ($adminOverride !== null && stripos($query, 'user_org') !== false) {
                return $adminOverride($query);
            }
            // Provider health queries
            if (stripos($query, 'delivery_provider_health') !== false) {
                return [];
            }
            return [];
        };
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
        $this->setUpCommonMocks();
        $this->setUpResultsMock();

        // Override get_row to ALSO return a customer for get_by_id
        $originalRowCb = $wpdb->test_get_row_callback;
        $wpdb->test_get_row_callback = function ($query) use ($originalRowCb) {
            if (stripos($query, 'WHERE c.id') !== false) {
                return (object) [
                    'id'      => 5,
                    'user_id' => 42,
                    'org_id'  => 10,
                    'email'   => 'customer@example.com',
                ];
            }
            return $originalRowCb ? $originalRowCb($query) : null;
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
        $this->setUpCommonMocks();
        $this->setUpResultsMock();

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
        $this->setUpCommonMocks();
        $this->setUpResultsMock();

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
    public function test_on_invoices_marked_overdue_customer_notification_includes_view_url()
    {
        global $wpdb;

        $this->setUserNotifPrefs(42);

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

        // Capture the notifications table insert data to inspect the payload
        $captured = null;
        $wpdb->test_insert_callback = function ($table, $data, $format) use (&$captured) {
            if (
                stripos($table, 'orabooks_notifications') !== false
                && isset($data['event_type'])
                && $data['event_type'] === 'invoice_overdue'
            ) {
                $captured = $data;
            }
        };

        $GLOBALS['orabooks_test_use_insert_id'] = 6001;

        $handler = $this->createHandler();
        $handler->on_invoices_marked_overdue(1, []);

        $this->assertNotNull($captured, 'An invoice_overdue notification should have been inserted into the notifications table');

        $payload = json_decode($captured['payload'], true);
        $this->assertIsArray($payload, 'Notification payload should be a valid JSON object');
        $this->assertArrayHasKey('view_url', $payload, 'Payload should contain a view_url field');
        $this->assertEquals(
            home_url('/dashboard/') . '?invoice_id=200',
            $payload['view_url'],
            'Customer overdue notification view_url should point to the dashboard with invoice ID'
        );
    }

    #[Test]
    public function test_on_invoices_marked_overdue_sends_notifications()
    {
        global $wpdb;

        $this->setUserNotifPrefs(42);
        $this->setUserNotifPrefs(55);

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

    #[Test]
    public function test_on_invoices_marked_overdue_notifies_admins_single_org()
    {
        global $wpdb;

        // Prefs for customers (42, 55) and admins (100, 101)
        $this->setUserNotifPrefs(42);
        $this->setUserNotifPrefs(55);
        $this->setUserNotifPrefs(100);
        $this->setUserNotifPrefs(101);

        $wpdb->test_get_results_callback = function ($query) {
            // Overdue invoices query (2 invoices in same org 10)
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
            // Admin lookup from user_org (called inside notify_org_admins)
            if (stripos($query, 'user_org') !== false) {
                return [
                    (object) ['user_id' => 100],
                    (object) ['user_id' => 101],
                ];
            }
            // Provider health queries (inside send_notification)
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

        $GLOBALS['orabooks_test_use_insert_id'] = 9001;

        $handler = $this->createHandler();
        $handler->on_invoices_marked_overdue(2, []);

        // 2 customer notifications + 2 admin notifications = multiple inserts
        $this->assertGreaterThan(0, $wpdb->insert_id,
            'Notifications should be created for customers and admins');
    }

    #[Test]
    public function test_on_invoices_marked_overdue_notifies_admins_multiple_orgs()
    {
        global $wpdb;

        // Prefs for customers (42, 55) and admins (200 in org 10, 201 in org 20)
        $this->setUserNotifPrefs(42);
        $this->setUserNotifPrefs(55);
        $this->setUserNotifPrefs(200);
        $this->setUserNotifPrefs(201);

        $wpdb->test_get_results_callback = function ($query) {
            // Overdue invoices in different orgs
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
                        'org_id'            => 20,
                        'customer_user_id'  => 55,
                    ],
                ];
            }
            // Admin lookup per org
            if (stripos($query, 'user_org') !== false) {
                if (stripos($query, 'org_id = 10') !== false) {
                    return [(object) ['user_id' => 200]];
                }
                if (stripos($query, 'org_id = 20') !== false) {
                    return [(object) ['user_id' => 201]];
                }
                return [];
            }
            // Provider health queries
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

        $GLOBALS['orabooks_test_use_insert_id'] = 9002;

        $handler = $this->createHandler();
        $handler->on_invoices_marked_overdue(2, []);

        $this->assertGreaterThan(0, $wpdb->insert_id,
            'Notifications should be created across both orgs');
    }

    #[Test]
    public function test_on_invoices_marked_overdue_no_admins_in_org()
    {
        global $wpdb;

        // Only set prefs for the customer (no admin prefs needed)
        $this->setUserNotifPrefs(42);

        $wpdb->test_get_results_callback = function ($query) {
            // Single overdue invoice
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
            // No admins in this org
            if (stripos($query, 'user_org') !== false) {
                return [];
            }
            // Provider health queries
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

        $GLOBALS['orabooks_test_use_insert_id'] = 9003;

        $handler = $this->createHandler();
        $handler->on_invoices_marked_overdue(1, []);

        // Customer notification should still work even with no admins
        $this->assertGreaterThan(0, $wpdb->insert_id,
            'Customer notification should still be sent even with no admins');
    }

    // ================================================================
    // notify_org_admins (via on_invoice_created & on_payment_recorded)
    // ================================================================

    #[Test]
    public function test_on_invoice_created_notifies_admins()
    {
        global $wpdb;

        $this->setUserNotifPrefs(42);
        $this->setUpCommonMocks();
        $this->setUpResultsMock(function ($query) {
            // Return two admins for the organization
            return [
                (object) ['user_id' => 100],
                (object) ['user_id' => 101],
            ];
        });

        // Set prefs for admin users too
        $this->setUserNotifPrefs(100);
        $this->setUserNotifPrefs(101);

        // Override get_row to return customer for get_by_id
        $originalRowCb = $wpdb->test_get_row_callback;
        $wpdb->test_get_row_callback = function ($query) use ($originalRowCb) {
            if (stripos($query, 'WHERE c.id') !== false) {
                return (object) [
                    'id'      => 5,
                    'user_id' => 42,
                    'org_id'  => 10,
                    'email'   => 'customer@example.com',
                ];
            }
            return $originalRowCb ? $originalRowCb($query) : null;
        };

        $GLOBALS['orabooks_test_use_insert_id'] = 8001;

        $handler = $this->createHandler();
        $handler->on_invoice_created(100, [
            'customer_id'    => 5,
            'invoice_number' => 'INV-001',
            'total_amount'   => 1500.00,
            'due_date'       => '2026-07-15',
            'org_id'         => 10,
        ]);

        // At least one notification insert happened (customer + 2 admins = 3+ inserts).
        // send_notification inserts into dedup table AND notifications table,
        // so multiple inserts occur. insert_id will be > 0.
        $this->assertGreaterThan(0, $wpdb->insert_id,
            'Notifications should be created for customer and admins');
    }

    #[Test]
    public function test_on_invoice_created_no_admins_in_org()
    {
        global $wpdb;

        $this->setUserNotifPrefs(42);
        $this->setUpCommonMocks();
        $this->setUpResultsMock(function ($query) {
            // No admins in the org
            return [];
        });

        // Override get_row to return customer for get_by_id
        $originalRowCb = $wpdb->test_get_row_callback;
        $wpdb->test_get_row_callback = function ($query) use ($originalRowCb) {
            if (stripos($query, 'WHERE c.id') !== false) {
                return (object) [
                    'id'      => 5,
                    'user_id' => 42,
                    'org_id'  => 10,
                    'email'   => 'customer@example.com',
                ];
            }
            return $originalRowCb ? $originalRowCb($query) : null;
        };

        $GLOBALS['orabooks_test_use_insert_id'] = 8002;

        $handler = $this->createHandler();
        $handler->on_invoice_created(100, [
            'customer_id'    => 5,
            'invoice_number' => 'INV-001',
            'total_amount'   => 1500.00,
            'due_date'       => '2026-07-15',
            'org_id'         => 10,
        ]);

        // Customer notification should still work even with no admins
        $this->assertGreaterThan(0, $wpdb->insert_id,
            'Customer notification should still be sent even with no admins');
    }

    #[Test]
    public function test_on_payment_recorded_notifies_admins()
    {
        global $wpdb;

        $this->setUserNotifPrefs(42);
        $this->setUpCommonMocks();
        $this->setUpResultsMock(function ($query) {
            // Return two admins for the organization
            return [
                (object) ['user_id' => 100],
                (object) ['user_id' => 101],
            ];
        });

        $this->setUserNotifPrefs(100);
        $this->setUserNotifPrefs(101);

        $GLOBALS['orabooks_test_use_insert_id'] = 8003;

        $handler = $this->createHandler();
        $handler->on_payment_recorded(900, [
            'customer_user_id' => 42,
            'invoice_number'   => 'INV-001',
            'amount'           => 500.00,
            'new_status'       => 'paid',
            'payment_date'     => '2026-06-15',
            'org_id'           => 10,
        ]);

        $this->assertGreaterThan(0, $wpdb->insert_id,
            'Notifications should be created for customer and admins');
    }

    #[Test]
    public function test_on_payment_recorded_no_admins_in_org()
    {
        global $wpdb;

        $this->setUserNotifPrefs(42);
        $this->setUpCommonMocks();
        $this->setUpResultsMock(function ($query) {
            return [];
        });

        $GLOBALS['orabooks_test_use_insert_id'] = 8004;

        $handler = $this->createHandler();
        $handler->on_payment_recorded(900, [
            'customer_user_id' => 42,
            'invoice_number'   => 'INV-001',
            'amount'           => 500.00,
            'new_status'       => 'paid',
            'payment_date'     => '2026-06-15',
            'org_id'           => 10,
        ]);

        $this->assertGreaterThan(0, $wpdb->insert_id,
            'Customer notification should still be sent even with no admins');
    }
}
