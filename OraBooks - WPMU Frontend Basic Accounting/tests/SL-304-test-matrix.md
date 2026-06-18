# SL-304 Test Matrix

## Unit Tests

- Open period posting succeeds: `FiscalPeriodStateMachineTest::test_open_period_posting_succeeds`
- Soft closed posting blocked: `FiscalPeriodStateMachineTest::test_soft_closed_posting_blocked`
- Hard closed posting blocked: `FiscalPeriodStateMachineTest::test_hard_closed_posting_blocked`
- Reopen soft close succeeds: `FiscalPeriodStateMachineTest::test_reopen_soft_close_succeeds`
- Hard close cannot reopen by Owner: `FiscalPeriodStateMachineTest::test_hard_close_cannot_reopen_by_owner`
- Super Admin override succeeds: `FiscalPeriodStateMachineTest::test_super_admin_override_succeeds`
- Illegal transitions return validation errors: `FiscalPeriodStateMachineTest::test_illegal_transition_returns_validation_error`

## Integration Tests

- Create two non-overlapping periods in the same organization.
- Attempt duplicate `(org_id, period_type, period_start)` and assert repository returns a duplicate/overlap error.
- Attempt overlapping date ranges for the same organization and same period type and assert repository rejection.
- Create a fiscal-year period and a month period inside that year and assert both can coexist.
- Create identical period dates across two WordPress blogs and assert each tenant is isolated.

## Authorization Tests

- Owner/Admin can close an open period.
- Accountant/Viewer cannot close or reopen periods.
- Owner/Admin cannot override reopen a hard-closed period.
- Super Admin can override reopen a hard-closed period with justification.
- Cross-tenant direct ID access returns not found.

## API Tests

- `GET /wp-json/api/fiscal-periods` returns only current organization rows with pagination metadata.
- `GET /wp-json/api/fiscal-periods/{id}` blocks cross-tenant access.
- `POST /wp-json/api/fiscal-periods/{id}/close` soft closes an open period.
- `POST /wp-json/api/fiscal-periods/{id}/reopen` requires a reason.
- `POST /wp-json/api/fiscal-periods/{id}/override-reopen` requires Super Admin and justification.

## Posting Engine Tests

- Journal posting in an open period inserts header and lines.
- Journal posting in a soft-closed period returns HTTP-style 409 message: "Fiscal period is closed. Cannot post."
- Journal posting in a hard-closed period returns HTTP-style 409 message: "Fiscal period is locked. Cannot post."
- Journal posting with no matching fiscal period returns conflict before insert.

## Audit Tests

- Soft close appends `period_closed`.
- Hard close appends `period_hard_closed`.
- Reopen appends `period_reopened`.
- Super Admin override appends `period_override_reopened`.
- Audit rows are never updated by fiscal-period write flows.

## Concurrency Tests

- Two concurrent overlapping create attempts leave only one fiscal period.
- Two concurrent close attempts on the same open period leave one successful transition and one conflict.
- Monthly/year-end generators can run multiple times and remain idempotent.
