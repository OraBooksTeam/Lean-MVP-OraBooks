<?php
/**
 * Focused SL-304 domain tests.
 *
 * Run inside a WordPress PHPUnit bootstrap after loading the accounting plugin.
 */

use PHPUnit\Framework\TestCase;

final class FiscalPeriodStateMachineTest extends TestCase {
    private function period($status) {
        return new OBN_Fiscal_Period((object) [
            'id' => 10,
            'org_id' => 1,
            'period_type' => 'MONTH',
            'period_name' => 'January 2026',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'status' => $status,
        ]);
    }

    public function test_open_period_posting_succeeds() {
        $this->assertTrue($this->period(OBN_Fiscal_Period_Status::OPEN)->can_post());
    }

    public function test_soft_closed_posting_blocked() {
        $this->assertFalse($this->period(OBN_Fiscal_Period_Status::SOFT_CLOSED)->can_post());
    }

    public function test_hard_closed_posting_blocked() {
        $this->assertFalse($this->period(OBN_Fiscal_Period_Status::HARD_CLOSED)->can_post());
    }

    public function test_reopen_soft_close_succeeds() {
        $this->assertTrue($this->period(OBN_Fiscal_Period_Status::SOFT_CLOSED)->reopen());
    }

    public function test_hard_close_cannot_reopen_by_owner() {
        $result = $this->period(OBN_Fiscal_Period_Status::HARD_CLOSED)->reopen(false);
        $this->assertInstanceOf(WP_Error::class, $result);
    }

    public function test_super_admin_override_succeeds() {
        $this->assertTrue($this->period(OBN_Fiscal_Period_Status::HARD_CLOSED)->reopen(true));
    }

    public function test_illegal_transition_returns_validation_error() {
        $result = $this->period(OBN_Fiscal_Period_Status::SOFT_CLOSED)->close_hard();
        $this->assertInstanceOf(WP_Error::class, $result);
    }
}
