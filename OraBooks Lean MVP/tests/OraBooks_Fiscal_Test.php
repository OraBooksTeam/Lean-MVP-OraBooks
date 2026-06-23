<?php
/**
 * Unit Tests for OraBooks_Fiscal
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Fiscal_Test extends TestCase
{
 protected function setUp: void
 {
 parent::setUp;

 $GLOBALS['orabooks_test_log_events'] = [];

 global $wpdb;
 $wpdb->test_get_var_callback = null;
 $wpdb->test_get_row_callback = null;
 $wpdb->test_get_results_callback = null;
 $wpdb->test_query_callback = null;
 $wpdb->test_update_callback = null;
 $wpdb->insert_id = 0;
 $wpdb->last_query = '';
 $wpdb->last_result = [];
 }

 #[Test]
 public function test_can_post_blocks_closed_period
 {
 global $wpdb;

 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'fiscal_periods') !== false) {
 return 'soft_closed';
 }
 return null;
 };

 $result = OraBooks_Fiscal::can_post(4, '2026-06-15');
 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('fiscal_closed', $result->get_error_code);
 $this->assertSame(409, $result->get_error_data['status']);
 }

 #[Test]
 public function test_can_post_allows_open_period
 {
 global $wpdb;

 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'fiscal_periods') !== false) {
 return 'open';
 }
 return null;
 };

 $result = OraBooks_Fiscal::can_post(4, '2026-06-15');
 $this->assertTrue($result);
 }

 #[Test]
 public function test_close_period_rejects_non_open_status
 {
 global $wpdb;

 $wpdb->test_get_row_callback = function {
 return (object) [
 'id' => 10,
 'org_id' => 2,
 'period_start' => '2026-06-01',
 'period_end' => '2026-06-30',
 'status' => 'soft_closed',
 ];
 };

 $result = OraBooks_Fiscal::close_period(10, 2, 'soft', 1);
 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('invalid_status', $result->get_error_code);
 }

 #[Test]
 public function test_reopen_period_requires_reason
 {
 $result = OraBooks_Fiscal::reopen_period(10, 2, 1, ' ');
 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('reason_required', $result->get_error_code);
 }

 #[Test]
 public function test_reopen_period_blocks_hard_closed
 {
 global $wpdb;

 $wpdb->test_get_row_callback = function {
 return (object) [
 'id' => 10,
 'org_id' => 2,
 'period_start' => '2026-05-01',
 'period_end' => '2026-05-31',
 'status' => 'hard_closed',
 ];
 };

 $result = OraBooks_Fiscal::reopen_period(10, 2, 1, 'Correction needed');
 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('hard_closed', $result->get_error_code);
 }

 #[Test]
 public function test_is_period_hard_closed
 {
 global $wpdb;

 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'fiscal_periods') !== false) {
 return 'hard_closed';
 }
 return null;
 };

 $this->assertTrue(OraBooks_Fiscal::is_period_hard_closed(2, '2026-05-15'));
 }

 #[Test]
 public function test_override_reopen_period_requires_hard_closed
 {
 global $wpdb;

 $wpdb->test_get_row_callback = function {
 return (object) [
 'id' => 10,
 'org_id' => 2,
 'period_start' => '2026-05-01',
 'period_end' => '2026-05-31',
 'status' => 'open',
 ];
 };

 $result = OraBooks_Fiscal::override_reopen_period(10, 2, 1, 'Audit correction');
 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('invalid_status', $result->get_error_code);
 }

 #[Test]
 public function test_paginate_periods_filters_by_status
 {
 global $wpdb;

 $wpdb->test_get_results_callback = function ($query) {
 if (stripos($query, 'fiscal_periods') !== false) {
 return [
 (object) ['id' => 1, 'org_id' => 2, 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'open'],
 (object) ['id' => 2, 'org_id' => 2, 'period_start' => '2026-05-01', 'period_end' => '2026-05-31', 'status' => 'soft_closed'],
 ];
 }
 return [];
 };

 $result = OraBooks_Fiscal::paginate_periods(2, ['status' => 'open']);
 $this->assertCount(1, $result['items']);
 $this->assertSame('open', $result['items'][0]['status']);
 }

 #[Test]
 public function test_close_period_requires_hard_confirm_for_hard_close
 {
 global $wpdb;

 $wpdb->test_get_row_callback = function {
 return (object) [
 'id' => 10,
 'org_id' => 2,
 'period_start' => '2026-06-01',
 'period_end' => '2026-06-30',
 'status' => 'open',
 ];
 };

 $result = OraBooks_Fiscal::close_period(10, 2, 'hard', 1, 'note', ['hard_confirm' => false]);
 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('hard_confirm_required', $result->get_error_code);
 }

 #[Test]
 public function test_can_reverse_blocks_hard_closed_period
 {
 global $wpdb;

 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'fiscal_periods') !== false) {
 return 'hard_closed';
 }
 return null;
 };

 $result = OraBooks_Fiscal::can_reverse(2, '2026-05-15');
 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('fiscal_hard_closed', $result->get_error_code);
 }

 #[Test]
 public function test_can_modify_account_structure_blocks_after_close
 {
 global $wpdb;

 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'fiscal_periods') !== false) {
 return 1;
 }
 return 0;
 };

 $result = OraBooks_Fiscal::can_modify_account_structure(2);
 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('fiscal_account_locked', $result->get_error_code);
 }

 #[Test]
 public function test_reopen_soft_closed_period_succeeds
 {
 global $wpdb;

 $wpdb->test_get_row_callback = function {
 return (object) [
 'id' => 10,
 'org_id' => 2,
 'period_start' => '2026-05-01',
 'period_end' => '2026-05-31',
 'status' => 'soft_closed',
 ];
 };
 $wpdb->test_update_callback = function {
 return 1;
 };

 $result = OraBooks_Fiscal::reopen_period(10, 2, 1, 'Month-end correction');
 $this->assertTrue($result);
 }

 #[Test]
 public function test_create_period_rejects_overlap
 {
 global $wpdb;

 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'fiscal_periods') !== false && stripos($query, 'SELECT id') !== false) {
 return 5;
 }
 return null;
 };

 $result = OraBooks_Fiscal::create_period(2, '2026-07-01', '2026-09-30');
 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('duplicate_period', $result->get_error_code);
 }

 #[Test]
 public function test_update_period_succeeds_on_open_period
 {
 global $wpdb;

 $wpdb->test_get_row_callback = function {
 return (object) [
 'id' => 10,
 'org_id' => 2,
 'period_start' => '2026-07-01',
 'period_end' => '2026-09-30',
 'status' => 'open',
 ];
 };
 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'fiscal_periods') !== false && stripos($query, 'SELECT id') !== false) {
 return 0;
 }
 if (stripos($query, 'journals') !== false) {
 return 0;
 }
 return null;
 };
 $wpdb->test_update_callback = function {
 return 1;
 };

 $result = OraBooks_Fiscal::update_period(10, 2, [
 'period_start' => '2026-07-05',
 'period_end' => '2026-09-25',
 ], 1);
 $this->assertTrue($result);
 }

 #[Test]
 public function test_update_period_blocks_soft_closed
 {
 global $wpdb;

 $wpdb->test_get_row_callback = function {
 return (object) [
 'id' => 10,
 'org_id' => 2,
 'period_start' => '2026-06-01',
 'period_end' => '2026-06-30',
 'status' => 'soft_closed',
 ];
 };

 $result = OraBooks_Fiscal::update_period(10, 2, [
 'period_start' => '2026-06-01',
 'period_end' => '2026-06-30',
 ], 1);
 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('invalid_status', $result->get_error_code);
 }

 #[Test]
 public function test_update_period_blocks_hard_closed
 {
 global $wpdb;

 $wpdb->test_get_row_callback = function {
 return (object) [
 'id' => 10,
 'org_id' => 2,
 'period_start' => '2026-05-01',
 'period_end' => '2026-05-31',
 'status' => 'hard_closed',
 ];
 };

 $result = OraBooks_Fiscal::update_period(10, 2, [
 'period_start' => '2026-05-01',
 'period_end' => '2026-05-31',
 ], 1);
 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('invalid_status', $result->get_error_code);
 }

 #[Test]
 public function test_update_period_rejects_overlap
 {
 global $wpdb;

 $wpdb->test_get_row_callback = function {
 return (object) [
 'id' => 10,
 'org_id' => 2,
 'period_start' => '2026-07-01',
 'period_end' => '2026-09-30',
 'status' => 'open',
 ];
 };
 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'fiscal_periods') !== false && stripos($query, 'SELECT id') !== false) {
 return 11;
 }
 return null;
 };

 $result = OraBooks_Fiscal::update_period(10, 2, [
 'period_start' => '2026-08-01',
 'period_end' => '2026-10-31',
 ], 1);
 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('duplicate_period', $result->get_error_code);
 }
}
