# SL-014 Phase 0 -> Phase 1 Progress Report

Date: 2026-07-02

## Phase 0 - Baseline Lock (Completed)

### Scope
- Focused path: Team invitation -> login -> redirect resolution.
- Known user issue: invited member can be sent back to accept-invite flow and not consistently land in workspace/dashboard.

### Baseline verification executed
- Team suite (before code changes):
  - Command:
    - `php tests/vendor/bin/phpunit --configuration \\10.124.1.254\Jahid_ Shared_Folder\Project Share Folder\Lean MVP OraBooks\OraBooks Lean MVP\tests\phpunit.xml --testsuite "OraBooks Team Tests"`
  - Result:
    - `OK (21 tests, 60 assertions)`

### Baseline finding
- Existing tests were green but did not cover the invite-login redirect loop scenario.

## Phase 1 - Invite Login Auto-Onboarding + Redirect Hardening (Completed)

### Implemented changes
1. Backend auto-onboarding on login when pending invite exists:
   - File: `includes/class-orabooks-auth.php`
   - Added `try_auto_onboard_pending_invite()`.
   - During tier-selection gating, if pending invite exists, system now attempts to accept pending invite automatically and issue a normal authenticated session.
   - On success, payload marks `invite_onboarded=true`.

2. Frontend redirect guard hardening:
   - File: `orabooks-ui/src/pages/frontend/pages/LoginPage.tsx`
   - Pending invite token now forces `/accept-invite` only when org is still unresolved.
   - If org is already resolved, login follows normal workspace redirect path.

3. New automated regression coverage:
   - File: `tests/OraBooks_Auth_Test.php`
   - Added test:
     - `test_login_customer_with_pending_invite_auto_onboards_and_returns_session`
   - Ensures login returns a full session (`token`, `refresh_token`, resolved `org_id`) and no `needs_accept_invite` loop when invite can be auto-accepted.

### Validation after changes
1. Auth suite:
- Command:
  - `php tests/vendor/bin/phpunit --configuration \\10.124.1.254\Jahid_ Shared_Folder\Project Share Folder\Lean MVP OraBooks\OraBooks Lean MVP\tests\phpunit.xml --testsuite "OraBooks Auth Tests"`
- Result:
  - `OK (100 tests, 303 assertions)`

2. Team suite:
- Command:
  - `php tests/vendor/bin/phpunit --configuration \\10.124.1.254\Jahid_ Shared_Folder\Project Share Folder\Lean MVP OraBooks\OraBooks Lean MVP\tests\phpunit.xml --testsuite "OraBooks Team Tests"`
- Result:
  - `OK (21 tests, 60 assertions)`

3. Static diagnostics:
- Checked modified files with workspace diagnostics tool.
- Result: no new errors reported.

## Current status
- Phase 0: Complete.
- Phase 1: Complete.
- Invite-login path is now hardened to prevent stale accept-invite loops when membership can be resolved at login.

## Recommended next phase
- Phase 2 (spec hardening):
  - Disallow owner assignment through role-update endpoint.
  - Tighten invite-accept transaction locks to align with SL-014 race-condition requirements.
  - Align status codes and endpoint semantics with SL-014 checklist.

## Phase 2 - Spec Hardening (Completed)

### Implemented changes
1. Block owner assignment via role-update endpoint:
   - File: `includes/class-orabooks-team.php`
   - `update_role()` now accepts only non-owner roles (`admin`, `approver`, `staff`, `viewer`) through this endpoint.

2. Invite acceptance lock hardening:
   - File: `includes/class-orabooks-team.php`
   - Added explicit `FOR UPDATE` locking on target user row during invite acceptance, including preloaded-user path.
   - Membership existence check now uses `FOR UPDATE` for race-safe check-before-insert behavior.

3. Invite API status semantics:
   - File: `includes/class-orabooks-team.php`
   - `ajax_invite_user()` now returns `409` when invite fails with `already_member`.

4. New Team tests for Phase 2:
   - File: `tests/OraBooks_Team_Test.php`
   - Added:
     - `test_update_role_rejects_owner_assignment`
     - `test_accept_pending_invite_uses_row_locks_for_user_and_membership`

### Validation after Phase 2
1. Team suite:
- Command:
  - `php tests/vendor/bin/phpunit --configuration \\10.124.1.254\Jahid_ Shared_Folder\Project Share Folder\Lean MVP OraBooks\OraBooks Lean MVP\tests\phpunit.xml --testsuite "OraBooks Team Tests"`
- Result:
  - `OK (23 tests, 65 assertions)`

2. Auth suite regression:
- Command:
  - `php tests/vendor/bin/phpunit --configuration \\10.124.1.254\Jahid_ Shared_Folder\Project Share Folder\Lean MVP OraBooks\OraBooks Lean MVP\tests\phpunit.xml --testsuite "OraBooks Auth Tests"`
- Result:
  - `OK (100 tests, 303 assertions)`

3. Diagnostics:
- Checked modified files for workspace errors.
- Result: no new errors.

## Consolidated status
- Phase 0: Complete.
- Phase 1: Complete.
- Phase 2: Complete.
