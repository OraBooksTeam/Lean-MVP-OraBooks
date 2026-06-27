# SL-302 Event Bus & Domain Events - Completion and Test Guide

Prepared for: Morshed
Project: OraBooks Lean MVP
Date: 2026-06-27

## 1) Executive Summary
SL-302 Event Bus & Domain Events is implemented and operational in this project for Lean MVP scope.

Verification was done from current code and fresh automated test execution.

Status: COMPLETE FOR MVP SCOPE

## 2) What Has Been Implemented

### 2.1 Core Event Bus Runtime
- Central publish API is available through OraBooks_EventBus facade.
- Canonical runtime is implemented in OraBooks_Event_Module.
- Outbox-based asynchronous delivery is implemented.

Key files:
- includes/class-orabooks-event-bus.php
- includes/events/class-orabooks-event-module.php

### 2.2 Transactional Outbox + Retry + Dead Letter
- Outbox records are created with pending status.
- Worker polling processes pending records.
- Consumer failure triggers retry with retry_count tracking.
- Max retry exhaustion moves events to dead-letter state.
- Dead-letter replay and discard operations exist.

### 2.3 Idempotent Consumer Tracking
- Consumer processing log table exists.
- Outbox ID + consumer key uniqueness prevents duplicate side effects.

### 2.4 Domain Event Contract and Versioning
- event_version support is present and defaults to 1.
- Payload validation exists for key event types.
- Correlation ID propagation is implemented.
- Canonical event list is defined for final-report scope.

Reference:
- docs/domain-events.md

### 2.5 Event Consumers and Integrations
- Default consumers are registered in event module.
- State transition consumers are wired.
- Additional publishers are wired from multiple modules (workflow, exports, expenses, voice, csv, commission, classification, async queue).

### 2.6 Health and Operations
- Event bus health metrics are exposed for dashboard use.
- Poll-now, replay, replay-all, discard AJAX operations are available.
- Dead-letter review UI exists.

## 3) Evidence Reviewed

### 3.1 Core SL-302 Files
- includes/class-orabooks-event-bus.php
- includes/events/class-orabooks-event-module.php

### 3.2 Domain Event Documentation
- docs/domain-events.md

### 3.3 Test Suite
- tests/OraBooks_EventBus_Test.php

### 3.4 Operational Surface
- templates/events/dead-letter-replay.php
- includes/class-orabooks-ajax.php (event_bus_health exposure)

## 4) Automated Test Result (Fresh)

Command executed:
D:\xampp\php\php.exe tests\vendor\phpunit\phpunit\phpunit --configuration tests\phpunit.xml tests\OraBooks_EventBus_Test.php

Observed result:
- Tests: 12
- Assertions: 45
- Status: PASS

## 5) Manual Test Plan (How to Verify in Live Environment)

### 5.1 Publish to Outbox
1. Trigger a domain action that publishes an event (for example journal_posted or export_requested path).
2. Check outbox record creation.

Expected:
- Event row exists with status pending and valid payload.

### 5.2 Poll and Delivery
1. Trigger poll-now operation.
2. Observe event state transition from pending to sent.

Expected:
- Processed count increases.
- Sent count increases.

### 5.3 Retry and Dead Letter Flow
1. Simulate consumer failure.
2. Re-run worker until retry limit reached.

Expected:
- retry_count increments.
- Event ultimately moves to dead_letter after max retries.

### 5.4 Dead Letter Replay/Discard
1. Open dead-letter review screen.
2. Replay one event.
3. Replay all open events.
4. Discard a selected event.

Expected:
- Replay transitions event back into processing lifecycle.
- Discard marks entry as discarded and no longer pending action.

### 5.5 Tenant Safety Check
1. Attempt replay with mismatched org context.

Expected:
- Cross-tenant replay attempt is denied.

### 5.6 Health Visibility
1. Open dashboard where event bus health appears.
2. Validate pending/sent/dead-letter counts and status.

Expected:
- Health widget reflects current outbox/dead-letter state.

## 6) Sign-Off Checklist
- EventBus unit tests pass.
- Outbox publish and delivery verified.
- Retry to dead-letter path verified.
- Replay/discard operations verified.
- Cross-tenant replay guard verified.
- Dashboard health visibility verified.

## 7) Final Conclusion
SL-302 Event Bus & Domain Events is complete and testable for OraBooks Lean MVP scope.
Recommendation: Approve SL-302 for MVP sign-off.
