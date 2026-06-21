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

## Migration backlog (direct status bypass)

| Priority | Module | File | Pattern | Target |
|----------|--------|------|---------|--------|
| P1 | Journal | `class-orabooks-posting.php` | update status then `record_transition()` | `transition()` + after hook |
| P1 | Journal | `class-orabooks-approval.php` | direct `status` update | workflow + hook |
| P2 | Expense | `class-orabooks-expenses.php` | direct `workflow_status` | `transition('expense', …)` |
| P3 | Invoice | `class-orabooks-customers.php` | partial workflow + fallbacks | full `transition()` |
| P3 | Bill | `class-orabooks-vendors.php` | partial + `update_status=>false` | after_transition hook |
| P4 | Commission | `class-orabooks-commission.php` | direct `status` | `transition('commission', …)` |
| P5 | Voice | `class-orabooks-voice.php` | expense workflow_status | via expense module |

## Definition of done (SL-301 MVP)

- [x] No production path updates workflow fields without `OraBooks_Workflow::transition()`
- [x] Invalid transition → 409 + `invalid_state_transition` audit
- [x] FOR UPDATE + DB transaction on transition
- [x] Preconditions hook + after_transition hook
- [x] `state_machine_transitions.org_id` for tenant traceability
- [x] Audit `state_changed` + optional `state_transition` event
- [x] All 5 record types migrated (journal, invoice, bill, expense, commission)
- [x] Unit tests: valid, invalid, transaction, org_id, allowed_events
- [x] This document + transition matrices

## Phase 2 migration notes

- Journal: `journal_transition()` helper; post uses `post` then `lock` inside posting TX (`skip_transaction`)
- Expense: `reject` event added; submit routes to `submit` or `ai_review`
- Bill/Invoice: `row_updates` for approval/post metadata (no `update_status => false`)
- Commission: `pay` / `expire` per earned row
- Voice/CSV: create draft → workflow send/submit

## Dependencies

- SL-009 Audit log
- SL-302 Event bus (optional `state_transition` publish)
- SL-003 RBAC (AJAX guards)
