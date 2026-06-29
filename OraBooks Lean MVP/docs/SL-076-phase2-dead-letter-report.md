# SL-076 Phase 2 Completion Report

Date: 2026-06-29
Phase: 2 - Dead-Letter Completion
Status: Completed

## 1) Objective

Implement and verify dead-letter archival behavior for terminal retry exhaustion in AI review queue processing.

## 2) Work Done

1. Added dead-letter archival write path in SL-076 worker escalation flow.
2. Added `dead_letter` history action logging when item is archived to dead-letter table.
3. Preserved queue item visibility as `escalated` to keep approver UI continuity while still storing forensic dead-letter payload.
4. Added PHPUnit coverage for terminal retry path validating:
   - insert into dead-letter table
   - dead_letter action in history log

## 3) Files Created/Updated in This Phase

### Created

1. docs/SL-076-phase2-dead-letter-report.md

### Updated

1. includes/class-orabooks-ai-review.php
2. tests/OraBooks_Ai_Review_Test.php
3. docs/SL-076-phase-tracker.md

## 4) Technical Change Summary

### Backend

- `escalate_item()` now:
  1. sets item status to `escalated`
  2. publishes `ai_review_escalated`
  3. copies full queue/evaluation payload to `ai_review_dead_letters`
  4. appends history action `dead_letter`

- New helper added:
  - `copy_to_dead_letters($item, array $evaluation, $reason)`

### Test

- Added `test_max_retry_escalation_copies_item_to_dead_letters_and_logs_history()`.

## 5) Test Commands Executed

### SL-076 focused

```powershell
D:\xampp\php\php.exe tests\vendor\phpunit\phpunit\phpunit --configuration tests\phpunit.xml tests\OraBooks_Ai_Review_Test.php
```

Result:
- PASS
- 5 tests, 19 assertions

### Regression set (Phase 1 baseline + new test)

```powershell
D:\xampp\php\php.exe tests\vendor\phpunit\phpunit\phpunit --configuration tests\phpunit.xml tests\OraBooks_Ai_Review_Test.php tests\OraBooks_Approval_Test.php tests\OraBooks_Workflow_Test.php tests\OraBooks_Workflow_Integration_Test.php tests\OraBooks_Notifications_Test.php
```

Result:
- PASS
- 76 tests, 153 assertions

## 6) How You Can Test Manually

1. Keep an AI-review queue item at low confidence/high risk with retry_count reaching threshold.
2. Trigger queue processor (cron run / worker tick).
3. Verify:
   - queue item becomes `escalated`
   - a row is created in `ai_review_dead_letters` with payload JSON
   - a `dead_letter` action row exists in `ai_review_history`

## 7) SQL Verification (Optional)

Use these checks:

```sql
SELECT id, queue_id, org_id, resource_type, moved_at
FROM wp_orabooks_ai_review_dead_letters
ORDER BY id DESC
LIMIT 10;
```

```sql
SELECT queue_id, action, created_at
FROM wp_orabooks_ai_review_history
WHERE action IN ('escalate','dead_letter')
ORDER BY id DESC
LIMIT 20;
```

## 8) Notes / Known Risk

Current Phase 2 implementation stores a dead-letter copy while keeping queue row as `escalated` for UI continuity and history foreign-key safety.

If strict physical move+delete is required later, schema adjustment is needed because `ai_review_history.queue_id` currently uses FK cascade behavior against queue records.

## 9) Next Phase Start Condition (Phase 3)

Proceed to Phase 3 only after confirming notification wiring target:

- `ai_review_escalated` event is consumed by notification layer.
- Approver/owner receive expected escalation notification.
- Event-to-notification path is covered by tests.
