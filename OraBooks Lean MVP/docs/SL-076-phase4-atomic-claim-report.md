# SL-076 Phase 4 Completion Report

Date: 2026-06-29
Phase: 4 - Atomic Claim Hardening
Status: Completed

## 1) Objective

Replace the old select-then-update queue claim race window with a transaction-safe atomic claim path using row locking semantics.

## 2) Work Done

1. Replaced `cron_process_queue()` list+update pattern with iterative atomic claim loop.
2. Added new `claim_next_item()` helper using:
   - `START TRANSACTION`
   - `SELECT ... FOR UPDATE SKIP LOCKED`
   - conditional claim update to `processing`
   - claim history write
   - `COMMIT` or `ROLLBACK`
3. Moved claim responsibility out of `process_queue_item()` so processing happens only after successful claim.
4. Added focused PHPUnit test to verify atomic claim query contains `FOR UPDATE SKIP LOCKED`.
5. Updated existing terminal-retry test to match the new claim path.

## 3) Files Created/Updated in This Phase

### Created

1. docs/SL-076-phase4-atomic-claim-report.md

### Updated

1. includes/class-orabooks-ai-review.php
2. tests/OraBooks_Ai_Review_Test.php
3. docs/SL-076-phase-tracker.md

## 4) Technical Change Summary

### Before

- Worker selected a batch with `get_results()`.
- Each row was later updated to `processing`.
- This left a race window where two workers could see the same pending row before update.

### After

- Worker now loops up to 5 times and claims exactly one item at a time using `claim_next_item()`.
- Claim query uses:

```sql
SELECT * FROM ai_review_queue
WHERE status = 'pending'
  AND (next_retry_at IS NULL OR next_retry_at <= ?)
  AND (lease_expires_at IS NULL OR lease_expires_at <= ?)
ORDER BY priority_score DESC, created_at ASC
LIMIT 1
FOR UPDATE SKIP LOCKED
```

- Claim transaction then:
  1. updates row to `processing`
  2. sets `processing_token`
  3. sets `lease_expires_at`
  4. writes `claim` history
  5. commits before actual processing starts

## 5) Test Commands Executed

### SL-076 focused

```powershell
D:\xampp\php\php.exe tests\vendor\phpunit\phpunit\phpunit --configuration tests\phpunit.xml tests\OraBooks_Ai_Review_Test.php
```

Result:
- PASS
- 6 tests, 21 assertions

### Regression set

```powershell
D:\xampp\php\php.exe tests\vendor\phpunit\phpunit\phpunit --configuration tests\phpunit.xml tests\OraBooks_Ai_Review_Test.php tests\OraBooks_Approval_Test.php tests\OraBooks_Workflow_Test.php tests\OraBooks_Workflow_Integration_Test.php tests\OraBooks_Notifications_Test.php
```

Result:
- PASS
- 78 tests, 160 assertions

## 6) Manual Test Instructions

1. Create multiple pending AI review items.
2. Trigger queue processor more than once in quick succession.
3. Confirm each item is claimed once and moves to `processing` without duplicate claim behavior.
4. Verify claim history rows exist with unique processing tokens.

## 7) Manual Verification Targets

### Queue row state

- `status = processing`
- `processing_token` populated
- `lease_expires_at` populated

### History row

- `action = claim`
- `details.token` present

## 8) Optional Query Checks

```sql
SELECT id, status, processing_token, lease_expires_at, next_retry_at
FROM wp_orabooks_ai_review_queue
ORDER BY id DESC
LIMIT 20;
```

```sql
SELECT queue_id, action, details, created_at
FROM wp_orabooks_ai_review_history
WHERE action = 'claim'
ORDER BY id DESC
LIMIT 20;
```

## 9) Result

Phase 4 objective achieved.

The prior race window between queue selection and processing claim has been closed at the application flow level by introducing transaction-scoped pessimistic claim logic.

## 10) Next Phase Start Condition (Phase 5)

Proceed to Phase 5 only after retry-policy refinement target is confirmed:

- Backoff timing remains deterministic.
- Retry counters and transitions are still correct after atomic-claim refactor.
- Focused retry/backoff tests can be expanded without breaking current path.
