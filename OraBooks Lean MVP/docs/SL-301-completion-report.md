# — Workflow State Engine
## Completion Report

| Field | Detail |
|-------|--------|
| **Ticket** | — Workflow State Engine |
| **Project** | OraBooks Lean MVP |
| **Status** | **Complete — MVP sign-off ready** |
| **Report date** | 20 June 2026 |
| **Related docs** | [workflow-state-engine.md](./workflow-state-engine.md) · [domain-events.md](./domain-events.md) |

---

## 1. Executive Summary

 delivers a **central workflow state engine** for OraBooks. Every business-record status change now flows through a single entry point — `OraBooks_Workflow::transition` — with validation, database locking, audit logging, and event publishing built in.

The implementation spans **five record types** (journal, invoice, bill, expense, commission), **three API surfaces** (PHP, guarded AJAX, REST), **RBAC/fiscal preconditions**, **observability metrics**, and **React UI** for all user-facing transitions including invoice cancel and bill void.

**Bottom line:** is functionally complete for Lean MVP. All Definition-of-Done items are checked. The only intentional deferral is a dynamic `state_machine_config` database table — the spec allows hard-coded machines for MVP.

---

## 2. Problem Statement

Before, workflow status fields (`status`, `workflow_status`) were updated directly in module code. That led to:

- Inconsistent validation across modules
- No unified audit trail for state changes
- Race conditions under concurrent requests
- Difficult tenant-scoped traceability
- No single place for RBAC/fiscal guards

 solves this by introducing a **state machine engine** with explicit transitions, hooks, and observability.

---

## 3. Solution Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│ Callers (UI / AJAX / REST) │
│ Journal · Invoice · Bill · Expense · Commission modules │
└────────────────────────────┬────────────────────────────────────┘
 │
 ▼
┌─────────────────────────────────────────────────────────────────┐
│ OraBooks_Workflow::transition │
│ ┌─────────────┐ ┌──────────────┐ ┌─────────────────────────┐ │
│ │ Validate │→ │ Preconditions│→ │ DB TX + FOR UPDATE lock │ │
│ │ transition │ │ (RBAC/MFA/ │ │ Update status + row │ │
│ │ │ │ fiscal) │ │ Persist transition log │ │
│ └─────────────┘ └──────────────┘ └───────────┬─────────────┘ │
│ │ │
│ Commit ──→ Audit + state_transition event │
└─────────────────────────────────────────────────────────────────┘
 │
 ┌───────────────────┼───────────────────┐
 ▼ ▼ ▼
 Audit Event Bus Observability
 state_changed read_model / workflow metrics
 invalid_transition notifications /
 webhook bridge
```

### Core design principles

1. **Single write path** — No production code updates workflow columns without `OraBooks_Workflow::transition`.
2. **Atomic transitions** — `START TRANSACTION` → `SELECT … FOR UPDATE` → validate → update → persist → `COMMIT` (or `ROLLBACK` on failure).
3. **Fail closed** — Invalid transitions return error (409) and log `invalid_state_transition`.
4. **Tenant traceability** — `state_machine_transitions.org_id` on every transition row.
5. **Extensibility** — Filter/action hooks for preconditions, row updates, and post-transition side effects.

---

## 4. Delivery Phases

| Phase | Scope | Status |
|-------|-------|--------|
| **Phase 0** | Inventory, state matrices, migration backlog, DoD checklist | ✅ Complete |
| **Phase 1** | Core engine: transactions, FOR UPDATE, hooks, AJAX, `org_id` column | ✅ Complete |
| **Phase 2** | Caller migration — all 5 record types use `transition` | ✅ Complete |
| **Phase 3** | Events, RBAC/fiscal preconditions, observability, health AJAX | ✅ Complete |
| **Phase 4** | Gap closure: cancel, void, expense lock, REST, MFA centralization | ✅ Complete |

---

## 5. State Machines (All Record Types)

### 5.1 Journal

| Column | `journals.status` |
|--------|-------------------|
| **States** | `draft` · `review_pending` · `approved` · `posted` · `locked` · `reversed` |

```
draft ──submit──► review_pending ──approve──► approved ──post──► posted ──lock──► locked
 │ reject ▼ │ reverse (from posted/locked)
 └──► draft └──► reversed
draft/approved ──edit──► draft
```

**Wired via:** `class-orabooks-posting.php`, `class-orabooks-approval.php`
**Note:** Post automatically chains `lock` (same pattern as expense).

---

### 5.2 Invoice

| Column | `invoices.workflow_status` |
|--------|----------------------------|
| **States** | `draft` · `sent` · `posted` · `cancelled` |

```
draft ──send──► sent ──post──► posted
 │ │
 └── cancel ─────┴── cancel ──► cancelled
```

**Wired via:** `send_invoice`, `post_invoice`, `cancel_invoice`
**UI:** Invoices page — Send · Post · Pay · **Cancel**

---

### 5.3 Bill

| Column | `bills.workflow_status` |
|--------|-------------------------|
| **States** | `draft` · `submitted` · `approved` · `posted` · `void` |

```
draft ──submit──► submitted ──approve──► approved ──post──► posted
 │ │ │
 └──── void ────────┴──── void ───────────┘ ──► void
```

**Wired via:** `submit_bill`, `approve_bill`, `post_bill`, `void_bill`
**UI:** Vendors page — Submit · Approve · Post · **Void**

---

### 5.4 Expense

| Column | `expenses.workflow_status` |
|--------|----------------------------|
| **States** | `draft` · `submitted` · `ai_review` · `approved` · `posted` · `locked` |

```
draft ──submit──► submitted ──approve──► approved ──post──► posted ──lock──► locked
 │ │ ai_review ▲ │ reject ▼
 └── ai_review ─┴────────────┘ └──► draft
```

**Wired via:** `class-orabooks-expenses.php` — submit, ai_review, approve, reject, post + auto lock
**Note:** Post chains `lock` transition (journal pattern).

---

### 5.5 Commission

| Column | `commissions_earned.status` |
|--------|-----------------------------|
| **States** | `earned` · `paid` · `expired` |

```
earned ──pay──► paid
 │
 └── expire ──► expired
```

**Wired via:** `class-orabooks-commission.php`

---

## 6. Key Files

### 6.1 Core engine & integration

| File | Role |
|------|------|
| `includes/class-orabooks-workflow.php` | State machines, `transition`, AJAX endpoints, health |
| `includes/class-orabooks-workflow-integration.php` | RBAC, fiscal, MFA, maker-checker preconditions |
| `includes/class-orabooks-database.php` | `state_machine_transitions` table + `org_id` migration |
| `includes/class-orabooks-rest-api.php` | `POST /api/internal/state/transition` |
| `includes/class-orabooks-observability.php` | Workflow metrics + health snapshot |
| `includes/class-orabooks-deploy-checks.php` | Post-deploy table verification |

### 6.2 Module callers (Phase 2 + gap closure)

| Module | File | Transitions |
|--------|------|-------------|
| Journal | `class-orabooks-posting.php`, `class-orabooks-approval.php` | submit, approve, reject, post, lock, reverse, edit |
| Invoice | `class-orabooks-customers.php` | send, post, **cancel** |
| Bill | `class-orabooks-vendors.php` | submit, approve, post, **void** |
| Expense | `class-orabooks-expenses.php` | submit, ai_review, approve, reject, post, **lock** |
| Commission | `class-orabooks-commission.php` | pay, expire |

### 6.3 Events ( consumers)

| File | Consumers |
|------|-----------|
| `includes/events/class-orabooks-event-module.php` | `workflow_read_model` · `workflow_notifications` · `job_enqueue_bridge` |

### 6.4 Frontend (React)

| File | Changes |
|------|---------|
| `orabooks-ui/src/pages/frontend/api.ts` | `invoiceCancel`, `billVoid` API helpers |
| `orabooks-ui/src/pages/frontend/pages/InvoicesPage.tsx` | Cancel button + confirm modal |
| `orabooks-ui/src/pages/frontend/pages/VendorsPage.tsx` | Void button + confirm modal |

### 6.5 Documentation

| File | Purpose |
|------|---------|
| `docs/workflow-state-engine.md` | Technical reference + DoD checklist |
| `docs/-completion-report.md` | This report |

### 6.6 Tests

| File | Coverage |
|------|----------|
| `tests/OraBooks_Workflow_Test.php` | Engine, rollback, FOR UPDATE lock, cancel transition |
| `tests/OraBooks_Workflow_Integration_Test.php` | Preconditions, MFA, maker-checker, expense lock |
| `tests/OraBooks_Observability_Test.php` | Workflow health metrics |
| `tests/OraBooks_Customers_Test.php` | Invoice cancel (4 cases) |
| `tests/OraBooks_Vendors_Test.php` | Bill void (3 cases) |
| `tests/OraBooks_Rest_Api_Test.php` | REST state transition validation |
| `tests/OraBooks_Deploy_Checks_Test.php` | `state_machine_transitions` table check |

---

## 7. API Reference

### 7.1 PHP (primary)

```php
$result = OraBooks_Workflow::transition('invoice', $invoice_id, 'cancel', [
 'user_id' => $user_id,
 'org_id' => $org_id,
 'reason' => 'Customer request',
 'row_updates' => [ /* optional extra columns */ ],
]);

$allowed = OraBooks_Workflow::allowed_events('bill', 'submitted');
// → ['approve', 'void']
```

**Context options:** `row_updates`, `skip_transaction`, `skip_preconditions`, `reason`, `mfa_otp`, `mfa_verified`

### 7.2 Module methods (business layer)

| Method | AJAX action | Permission |
|--------|-------------|------------|
| `OraBooks_Customers::cancel_invoice` | `orabooks_invoice_cancel` | `create_invoice` |
| `OraBooks_Vendors::void_bill` | `orabooks_bill_void` | `submit_transaction` / `manage_org_settings` |
| `OraBooks_Customers::send_invoice` | `orabooks_invoice_send` | `create_invoice` |
| `OraBooks_Customers::post_invoice` | `orabooks_invoice_post` | `create_invoice` |
| `OraBooks_Vendors::submit_bill` | `orabooks_bill_submit` | `submit_transaction` |
| `OraBooks_Vendors::approve_bill` | `orabooks_bill_approve` | `approve_journal` |
| `OraBooks_Vendors::post_bill` | `orabooks_bill_post` | `approve_journal` / `manage_org_settings` |

### 7.3 Generic workflow AJAX

| Action | Purpose |
|--------|---------|
| `orabooks_workflow_transitions` | Transition history for a record |
| `orabooks_workflow_allowed_events` | Allowed events from current state |
| `orabooks_workflow_transition` | Execute transition (guarded) |
| `orabooks_workflow_health` | Org-scoped workflow health snapshot |

### 7.4 REST

```
POST /wp-json/api/internal/state/transition
Headers: X-OraBooks-Org-Id: {org_id}
Body: record_type, record_id, event, reason?, mfa_otp?, mfa_verified?
```

Requires: `manage_settings` OR `submit_transaction` OR `approve_journal`

---

## 8. Cross-Cutting Concerns

### 8.1 Audit

| Event | When |
|-------|------|
| `state_changed` | Successful transition |
| `invalid_state_transition` | Rejected transition (409) |
| `workflow_transition_failed` | Precondition or publish failure |

### 8.2 Event bus

Every successful transition publishes `state_transition` with payload:

`org_id`, `record_type`, `record_id`, `event`, `from_state`, `to_state`

**Consumers:**

| Consumer | Behavior |
|----------|----------|
| `workflow_read_model` | Bumps read-model dues counters |
| `workflow_notifications` | Org admin notifications (non-journal) |
| `job_enqueue_bridge` | Async webhook dispatch |

### 8.3 RBAC & security

Centralized in `OraBooks_Workflow_Integration::apply_preconditions`:

- Journal: submit/approve/reject/post/reverse permissions + fiscal period checks
- Journal approve: **maker-checker** + **MFA for high-value** (threshold from approval policy)
- Expense: manage_expenses / approve_expense per event
- Invoice/Bill: create_invoice / manage_settings; cancel & void guarded
- Expense lock: internal post-step (no extra RBAC, matches journal lock)

### 8.4 Observability

| Metric | Description |
|--------|-------------|
| `workflow.transition_success_24h` | Successful transitions in last 24h |
| `workflow.transition_failure_24h` | Failed transitions in last 24h |
| `workflow.transition_success_count` | Per-transition counter |
| `workflow.transition_failure_count` | Per-failure counter |

**Dashboards:** `/observability` (org) · `/admin/observability` (platform)

---

## 9. Database

### Table: `state_machine_transitions`

Stores immutable transition history:

| Column | Purpose |
|--------|---------|
| `org_id` | Tenant traceability ( migration) |
| `record_type` | journal, invoice, bill, expense, commission |
| `record_id` | Primary key of business record |
| `from_state` / `to_state` | State change |
| `event` | Triggering event name |
| `triggered_by` | User ID |
| `reason` | Optional free text |
| `created_at` | Timestamp |

Verified in post-deploy checks (`OraBooks_DeployChecks`).

---

## 10. Business Rules (Cancel & Void)

### Invoice cancel

| Rule | Detail |
|------|--------|
| Allowed states | `draft`, `sent` |
| Blocked if | `paid`, `partial`, or `paid_amount > 0` |
| Side effect | Sets `payment_status = cancelled` |
| Posted invoices | Cannot cancel (use reversal flows separately) |

### Bill void

| Rule | Detail |
|------|--------|
| Allowed states | `draft`, `submitted`, `approved` |
| Blocked if | `paid`, `partial`, or `paid_amount > 0` |
| Posted bills | Cannot void (machine rule + business validation) |

---

## 11. Testing Summary

### focused tests

| Area | Tests | Result |
|------|-------|--------|
| Workflow engine | Validation, rollback, hooks, FOR UPDATE | ✅ Pass |
| Integration | RBAC, maker-checker, MFA, expense lock | ✅ Pass |
| Invoice cancel | Draft, sent, posted reject, payment reject | ✅ Pass |
| Bill void | Draft, posted reject, payment reject | ✅ Pass |
| REST transition | Required field validation | ✅ Pass |
| Observability | Org-scoped workflow health | ✅ Pass |
| Deploy checks | `state_machine_transitions` exists | ✅ Pass |

**Run command:**

```bash
php tests/vendor/bin/phpunit -c tests/phpunit.xml
```

** filter (32 tests):**

```bash
php tests/vendor/bin/phpunit -c tests/phpunit.xml \
 --filter "OraBooks_Workflow|OraBooks_Customers_Test::test_cancel|OraBooks_Vendors_Test::test_void|OraBooks_Approval_Test|OraBooks_Rest_Api_Test::test_rest_state|OraBooks_Workflow_Integration"
```

---

## 12. Live Verification Checklist

After auto-deploy from shared folder:

| # | Check | How |
|---|-------|-----|
| 1 | Deploy health | WP Admin → deploy checks green; `state_machine_transitions` exists |
| 2 | DB migration | First page load after deploy; `orabooks_db_version` current |
| 3 | Journal flow | draft → submit → approve → post (→ locked) |
| 4 | Invoice flow | create → send → pay → posted |
| 5 | Invoice cancel | draft invoice → Cancel button → `cancelled` |
| 6 | Bill flow | create → submit → approve → post |
| 7 | Bill void | draft bill → Void button → `void` |
| 8 | Expense flow | submit → approve → post (→ locked) |
| 9 | Commission | earned → pay |
| 10 | Audit log | `/audit-log` — `state_changed` entries |
| 11 | Observability | `/observability` — workflow metrics healthy |
| 12 | Negative test | Try invalid transition → blocked + audited |

**React UI note:** Run `npm run build` in `orabooks-ui` (from mapped drive, not UNC) so Cancel/Void buttons appear in compiled assets.

---

## 13. Definition of Done — Final Status

| # | Criterion | Status |
|---|-----------|--------|
| 1 | All workflow updates via `OraBooks_Workflow::transition` | ✅ |
| 2 | Invalid transition → 409 + audit | ✅ |
| 3 | FOR UPDATE + DB transaction | ✅ |
| 4 | Preconditions + after_transition hooks | ✅ |
| 5 | `state_machine_transitions.org_id` | ✅ |
| 6 | Audit + `state_transition` event + consumers | ✅ |
| 7 | 5 record types migrated | ✅ |
| 8 | Invoice cancel end-to-end | ✅ |
| 9 | Bill void end-to-end | ✅ |
| 10 | Expense post → lock | ✅ |
| 11 | REST internal transition route | ✅ |
| 12 | Journal MFA + maker-checker in preconditions | ✅ |
| 13 | Concurrency (FOR UPDATE) test | ✅ |
| 14 | Unit tests + observability | ✅ |
| 15 | Documentation | ✅ |

---

## 14. Intentional MVP Deferrals

| Item | Reason | Impact |
|------|--------|--------|
| `state_machine_config` DB table | Spec allows hard-coded machines for MVP; filter hook `orabooks_workflow_state_machines` available for extension | Low — no user impact |
| Dynamic per-org machine editing UI | Future admin feature | Low |
| Reconciliation workflow | Out of scope | N/A |

---

## 15. Dependency Map

```
 Workflow State Engine
 ├── RBAC (preconditions, AJAX guards)
 ├── Audit Log (state_changed, invalid_state_transition)
 ├── Approval (journal approve, MFA, maker-checker)
 ├── Event Bus (state_transition publish + 3 consumers)
 ├── Observability (workflow metrics, health dashboard)
 ├── Invoices (send, post, cancel)
 ├── Vendors/AP (submit, approve, post, void)
 └── REST API (internal state transition route)
```

---

## 16. Sign-Off Recommendation

** is recommended for MVP sign-off.**

All core engine requirements, caller migrations, event integration, observability, gap-closure items (cancel, void, expense lock, REST, MFA), tests, and documentation are complete. Remaining items are optional future enhancements explicitly allowed by the MVP spec.

---

## 17. Quick Links

| Resource | Path |
|----------|------|
| Technical reference | [docs/workflow-state-engine.md](./workflow-state-engine.md) |
| Core engine | `includes/class-orabooks-workflow.php` |
| Preconditions | `includes/class-orabooks-workflow-integration.php` |
| This report | `docs/-completion-report.md` |

---

*Generated for OraBooks Lean MVP — Workflow State Engine delivery.*
