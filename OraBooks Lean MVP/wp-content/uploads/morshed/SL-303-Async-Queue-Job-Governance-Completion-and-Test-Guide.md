# SL-303 Async Queue & Job Governance - Completion and Test Guide

Prepared for: Morshed
Project: OraBooks Lean MVP
Date: 2026-06-27

## 1) Executive Summary
SL-303 (Async Queue & Job Governance) is implemented and operational in this project for Lean MVP scope.

Verification basis:
- Core async queue engine code review
- UI/API governance and controls review
- Fresh automated test execution

Status: COMPLETE FOR MVP SCOPE

## 2) What Is Implemented

### 2.1 Core Queue Engine
- Central async queue runtime with enqueue + worker processing.
- Job handler registry by job_type.
- Queue scheduling and periodic processing hooks.

Primary file:
- includes/class-orabooks-async-queue.php

### 2.2 Job Lifecycle Governance
- Status lifecycle includes pending, processing, completed, failed, dead_letter, cancelled, discarded.
- FOR UPDATE style lock flow to avoid duplicate processing.
- Priority-based processing and queue_name support.
- Idempotency key dedupe support.

### 2.3 Retry and Backoff Policy
- Exponential backoff retry scheduling is implemented.
- retry_count and max_retries enforced.
- Max-retry exhaustion transitions jobs to dead_letter.
- Manual replay from dead_letter/failed is supported.

### 2.4 Dead Letter Governance and Actions
- Manual actions: replay, discard, cancel (state-gated).
- Dead-letter alert hooks are wired.
- Dead-letter notification dispatch integration exists.

### 2.5 Recovery, Monitoring, and Archival
- Heartbeat recovery for stuck processing jobs.
- Monitoring hooks for lag and dead-letter thresholds.
- Queue stats include pending/processing/completed/failed/dead_letter and failure rate.
- Completed job archival flow exists.

### 2.6 Security and Tenant Scope Controls
- Queue actions require management permission checks.
- Cross-tenant job replay/discard/cancel blocked by org scope checks.
- Org-scoped SQL filtering via payload.org_id for list/stats.

### 2.7 UI/API and Contract Surface
- Admin UI panel for queue stats and actions is implemented.
- AJAX endpoints exist for poll/replay/discard/cancel/stats and webhook settings.
- OpenAPI includes Async Queue tag and internal enqueue/retry endpoints.
- Webhook dispatch signing contract (HMAC headers) documented.

## 3) Evidence Reviewed

### 3.1 Core Implementation
- includes/class-orabooks-async-queue.php

### 3.2 Tests
- tests/OraBooks_AsyncQueue_Test.php

### 3.3 API/Contract
- docs/openapi/openapi.json

### 3.4 UI Operations Panel
- orabooks-ui/src/components/platform/JobQueuePanel.tsx

## 4) Automated Test Result (Fresh)

Command executed:
D:\xampp\php\php.exe tests\vendor\phpunit\phpunit\phpunit --configuration tests\phpunit.xml tests\OraBooks_AsyncQueue_Test.php

Observed result:
- Tests: 14
- Assertions: 43
- Status: PASS

## 5) Manual Test Plan (How to Test)

### 5.1 Enqueue and Dedupe
1. Enqueue a job with idempotency_key.
2. Enqueue the same job again with same key and same org scope.

Expected:
- Second enqueue returns existing job (deduped), no duplicate processing job created.

### 5.2 Processing Success Path
1. Register/trigger a valid handler job.
2. Run Poll Now from queue UI.

Expected:
- Job moves pending -> processing -> completed.
- completed_at is set.

### 5.3 Retry and Backoff Path
1. Trigger handler failure for a pending job.
2. Run Poll Now.

Expected:
- Job returns to pending with retry_count incremented.
- next_retry_at is scheduled (backoff delay).

### 5.4 Dead Letter Path
1. Continue failing same job until max_retries reached.

Expected:
- Job transitions to dead_letter.
- last_error captured.
- dead-letter alert hooks/notifications can fire.

### 5.5 Manual Governance Actions
1. From dead_letter or failed status, click Replay.
2. From dead_letter or failed status, click Discard.
3. For pending retry-wait job, click Cancel.

Expected:
- Replay resets to pending and retry_count reset.
- Discard sets status to discarded.
- Cancel sets status to cancelled only when pending.

### 5.6 Tenant-Scope Security
1. Attempt replay/discard/cancel using mismatched org scope.

Expected:
- Action denied with forbidden response.

### 5.7 Monitoring and Health Metrics
1. Open Job Queue panel and verify stats.
2. Check Recent Failures section.

Expected:
- Counts and failure metrics reflect real queue state.
- Retry actions from failure table work.

### 5.8 Webhook Dispatch Signing
1. Trigger webhook_dispatch job with signing_secret.
2. Inspect outbound request headers.

Expected:
- X-OraBooks-Webhook-Timestamp present.
- X-OraBooks-Webhook-Job-Id present.
- X-OraBooks-Webhook-Signature present and HMAC-SHA256 signed.

## 6) Sign-Off Checklist
- Async queue unit tests pass.
- Success path, retry path, and dead-letter path verified.
- Manual governance actions (replay/discard/cancel) verified.
- Cross-tenant protection verified.
- Queue health dashboard metrics verified.
- Webhook signing behavior verified.

## 7) Final Conclusion
SL-303 Async Queue & Job Governance is complete and testable for OraBooks Lean MVP scope.
Recommendation: Approve SL-303 for MVP sign-off.
