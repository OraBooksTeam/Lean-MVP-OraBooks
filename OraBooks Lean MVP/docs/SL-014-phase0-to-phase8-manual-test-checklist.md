# SL-014 Manual Test Checklist (Phase 0 to Phase 8)

Date: 2026-07-02
Scope: Team Management and Invite Lifecycle
Audience: Tenant Owner/Admin UAT, QA, Release Sign-off

## Test Preconditions

1. Prepare three accounts in one tenant:
- Owner account (full team permissions)
- Admin account (manage employees)
- Viewer or Staff account (no team management permissions)

2. Prepare one non-member account:
- Registered user not in the tenant organization

3. Prepare email access:
- Inbox for invited email to validate invite-link and acceptance flow

4. Confirm plugin pages:
- Login page
- Accept invite page
- Team page

5. Ensure audit log visibility:
- You can view invite/team events in logs or observability panel

## Phase 0 Manual Checks (Baseline)

1. Owner logs in and opens Team page.
Expected:
- Team page loads successfully.
- Existing members and pending invites render without error.

2. Trigger a normal refresh on Team page.
Expected:
- No redirect loop to accept-invite.
- Data remains stable.

## Phase 1 Manual Checks (Invite Login Auto-Onboarding)

1. Owner sends invite to a new email.
2. Invited user logs in with invited account and opens invite link flow.
Expected:
- User does not get stuck in login/accept loop.
- User lands into valid org workspace after acceptance.

## Phase 2 Manual Checks (Spec Hardening)

1. Invite existing member email again.
Expected:
- API/UI shows conflict behavior (already member).
- No duplicate membership created.

2. Try role update to owner via Team role editor.
Expected:
- Operation denied.
- Owner assignment cannot be done through generic role update endpoint.

## Phase 3 Manual Checks (Owner Immutability + Access)

1. On Team page, inspect owner row actions.
Expected:
- Owner row role is not editable.
- Owner row cannot be removed.

2. Login as Viewer/Staff and open Team dashboard path.
Expected:
- Access denied for team management operations.

## Phase 4 Manual Checks (Partner + Multi-org Doctrine)

1. If user belongs to multiple orgs, verify resolved org context matches latest valid membership.
Expected:
- Correct org context selected.
- No cross-org leakage.

2. In partner-type org, verify restricted permission behavior remains enforced.
Expected:
- Staff/Viewer do not receive elevated partner commission access by default.

## Phase 5 Manual Checks (Resend Safety + Accept Resilience)

1. Resend a valid pending invite.
Expected:
- Resend succeeds.
- New invite token link works.

2. Try resend/cancel on a used or missing invite.
Expected:
- Deterministic not-found behavior shown.
- UI refreshes and stale row disappears.

3. Attempt invite acceptance with unverified invited account.
Expected:
- Acceptance denied until email verified.

## Phase 6 Manual Checks (API/UI Contract + UX Consistency)

1. Trigger each major error path from Team actions:
- Permission denied
- Conflict (already member / owner locked)
- Rate limit
- Invalid invite

Expected:
- UI messages are clear and stable.
- Actions do not duplicate due to double-click.
- Status/code semantics behave consistently across invite/resend/cancel/update/remove.

2. Role change for non-owner member.
Expected:
- Success message appears.
- Relogin note shown where applicable.

## Phase 7 Manual Checks (Deep Regression Shield Parity)

1. Unauthenticated request path:
- Open endpoint/session path without valid session.
Expected:
- Not authenticated behavior.

2. Invalid org or org not found path.
Expected:
- Clear organization error behavior.

3. Inactive org path.
Expected:
- Operation blocked with inactive-organization behavior.

4. Non-member path.
Expected:
- Tenant membership isolation enforced.

## Phase 8 Manual Checks (Release Gate Sign-off)

1. Full owner-admin journey:
- Invite user
- Resend invite
- Accept invite
- Change role (non-owner)
- Remove non-owner

Expected:
- End-to-end path works without regressions.
- Audit events are recorded.

2. Negative journey:
- Viewer tries management actions.
- Non-member tries org actions.
- Owner row edit/remove attempted.

Expected:
- All blocked as designed.
- No unauthorized state mutation.

3. Email and link journey:
- Invite email received.
- Accept invite link lands on correct flow.

Expected:
- Link is valid during expiry window.
- Used/expired link is rejected cleanly.

## Sign-off Checklist

1. All automated suites are green:
- Team
- Auth
- RBAC
- Organization
- Frontend typecheck

2. Manual checks above are executed and recorded.
3. No unresolved P1/P2 defects remain.
4. Stakeholder approves release gate.

## Execution Log Template (for your run)

- Date:
- Tester name:
- Environment:
- Tenant org:
- Passed scenarios:
- Failed scenarios:
- Defect references:
- Final decision: Pass / Blocked
