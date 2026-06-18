<?php
/**
 * Unit Tests for OraBooks_Posting (SL-001)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Posting_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['orabooks_test_log_events'] = [];

        global $wpdb;
        $wpdb->test_get_var_callback = null;
        $wpdb->test_get_row_callback = null;
        $wpdb->test_get_results_callback = null;
        $wpdb->test_query_callback = null;
        $wpdb->test_insert_callback = null;
        $wpdb->test_update_callback = null;
        $wpdb->insert_id = 0;
        $wpdb->last_query = '';
        $wpdb->last_result = [];
    }

    #[Test]
    public function test_compute_canonical_hash_is_deterministic()
    {
        $lines = [
            (object) [
                'account_id' => 2,
                'account_code' => '2000',
                'debit_amount' => 0,
                'credit_amount' => 100,
            ],
            (object) [
                'account_id' => 1,
                'account_code' => '1000',
                'debit_amount' => 100,
                'credit_amount' => 0,
            ],
        ];

        $hashA = OraBooks_Posting::compute_canonical_hash(5, '2026-06-15', $lines, null, 'JE-2026-000001');
        $hashB = OraBooks_Posting::compute_canonical_hash(5, '2026-06-15', array_reverse($lines), null, 'JE-2026-000001');

        $this->assertSame($hashA, $hashB);
        $this->assertSame(64, strlen($hashA));
    }

    #[Test]
    public function test_reverse_journal_requires_reason()
    {
        $result = OraBooks_Posting::reverse_journal(1, 2, 9, '   ');
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('reason_required', $result->get_error_code());
    }

    #[Test]
    public function test_reverse_journal_rejects_non_posted_status()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function () {
            return (object) [
                'id' => 1,
                'org_id' => 2,
                'status' => 'approved',
                'journal_number' => 'JE-2026-000001',
                'transaction_date' => '2026-06-15',
                'posted_at' => null,
            ];
        };

        $result = OraBooks_Posting::reverse_journal(1, 2, 9, 'Correction needed');
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invalid_status', $result->get_error_code());
    }

    #[Test]
    public function test_validate_ledger_integrity_reports_hash_chain_break()
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'HAVING ABS') !== false) {
                return [];
            }
            if (stripos($query, 'status IN') !== false && stripos($query, 'journal_hash') !== false) {
                return [
                    (object) ['id' => 10, 'journal_hash' => 'hash-b', 'previous_hash' => 'hash-a', 'journal_number' => 'JE-1'],
                    (object) ['id' => 11, 'journal_hash' => 'hash-c', 'previous_hash' => 'wrong', 'journal_number' => 'JE-2'],
                ];
            }
            if (stripos($query, 'orabooks_accounts') !== false) {
                return [];
            }
            return [];
        };

        $result = OraBooks_Posting::validate_ledger_integrity(4);
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['issues']);
        $this->assertSame('hash_chain_break', $result['issues'][0]['type']);
    }
}
