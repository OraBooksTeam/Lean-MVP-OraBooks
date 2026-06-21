# SL-301 — Workflow State Engine

Central state machine for business records. All workflow status changes should go through `OraBooks_Workflow::transition()`.

## Supported record types

| Record type | Status column | States |
|-------------|---------------|--------|
| `journal` | `status` | draft → review_pending → approved → posted → locked → reversed |
| `invoice` | `workflow_status` | draft → sent → posted \| cancelled |
| `bill` | `workflow_status` | draft → submitted → approved → posted \| void |
| `expense` | `workflow_status` | draft → submitted \| ai_review → approved → posted → locked |
| `commission` | `status` | earned → paid \| expired |

## Journal state machine

```
draft --submit--> review_pending
review_pending --approve--> approved
review_pending --reject--> draft
approved --post--> posted
posted --lock--> locked
posted|locked --reverse--> reversed
draft|approved --edit--> draft
```

## API

```php
$result = OraBooks_Workflow::transition('bill', $bill_id, 'submit', [
    'user_id' => $user_id,
    'org_id'  => $org_id,
    'reason'  => optional,
]);

$events = OraBooks_Workflow::allowed_events('journal', 'draft'); // ['submit', 'edit']
```

### Hooks

- `orabooks_workflow_preconditions` — return `true` or `WP_Error` before transition
- `orabooks_workflow_after_transition` — side effects after successful commit
- `orabooks_workflow_state_machines` — filter machine definitions

### AJAX (JWT + RBAC)

- `orabooks_workflow_transitions` — history for a record
- `orabooks_workflow_allowed_events` — allowed events from current state
- `orabooks_workflow_transition` — execute transition (internal/guarded)
- `orabooks_invoice_cancel` — cancel draft/sent invoice
- `orabooks_bill_void` — void draft/submitted/approved bill

### REST (SL-304)

- `POST /wp-json/api/internal/state/transition` — guarded workflow transition (record_type, record_id, event, org_id)

## Migration status (Phase 2 — complete)

All modules now use `OraBooks_Workflow::transition()`. See git history for Phase 2 caller migration.

## Phase 3 — Events & observability (complete)

- `state_transition` SL-302 consumers:
  - `workflow_read_model` — read model dues bump
  - `workflow_notifications` — org admin notifications (non-journal)
  - `job_enqueue_bridge` — webhook async dispatch
- RBAC/fiscal preconditions: `class-orabooks-workflow-integration.php`
- Metrics: `workflow.transition_success_24h`, `workflow.transition_failure_24h`
- Org health AJAX: `orabooks_workflow_health`
- Platform dashboard: observability snapshot includes `workflow`

## Definition of done (SL-301 MVP)

- [x] No production path updates workflow fields without `OraBooks_Workflow::transition()`
- [x] Invalid transition → 409 + `invalid_state_transition` audit
- [x] FOR UPDATE + DB transaction on transition
- [x] Preconditions hook + after_transition hook
- [x] `state_machine_transitions.org_id` for tenant traceability
- [x] Audit `state_changed` + `state_transition` event + consumers
- [x] All 5 record types migrated (journal, invoice, bill, expense, commission)
- [x] Invoice cancel + bill void end-to-end (backend, AJAX, UI)
- [x] Expense post → lock workflow transition (matches journal pattern)
- [x] REST `POST /api/internal/state/transition`
- [x] Journal MFA + maker-checker in centralized preconditions
- [x] FOR UPDATE concurrency test
- [x] Unit tests + observability metrics
- [x] This document + transition matrices

## Dependencies

- SL-009 Audit log
- SL-302 Event bus (`state_transition` publish + consumers)
- SL-003 RBAC (preconditions + AJAX guards)
- SL-093 Observability (workflow metrics)
