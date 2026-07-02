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

## Phase 3 - Team Access + Owner Row UI/Backend Parity (Completed)

### Implemented changes
1. Team dashboard access restricted to Owner/Admin:
   - File: `includes/class-orabooks-ajax.php`
   - `ajax_team_dashboard()` now denies access with `403` if user does not have `manage_employees` permission.

2. Owner member immutability enforced in backend:
   - File: `includes/class-orabooks-team.php`
   - `update_role()` now blocks changing any owner row via team role endpoint (`owner_role_locked`).
   - `remove_user()` now blocks removing owner rows (`owner_remove_blocked`).

3. Owner row immutability reflected in frontend:
   - File: `orabooks-ui/src/pages/frontend/pages/TeamPage.tsx`
   - Role dropdown and Remove action are disabled for owner rows.

4. Team tests aligned with owner immutability contract:
   - File: `tests/OraBooks_Team_Test.php`
   - Updated expected error codes for owner role/update and owner remove behavior.

### Validation after Phase 3
1. Team suite:
- Command:
  - `php tests/vendor/bin/phpunit --configuration \\10.124.1.254\Jahid_ Shared_Folder\Project Share Folder\Lean MVP OraBooks\OraBooks Lean MVP\tests\phpunit.xml --testsuite "OraBooks Team Tests"`
- Result:
  - `OK (23 tests, 65 assertions)`

2. Auth regression suite:
- Command:
  - `php tests/vendor/bin/phpunit --configuration \\10.124.1.254\Jahid_ Shared_Folder\Project Share Folder\Lean MVP OraBooks\OraBooks Lean MVP\tests\phpunit.xml --testsuite "OraBooks Auth Tests"`
- Result:
  - `OK (100 tests, 303 assertions)`

3. Diagnostics:
- Checked modified files for workspace errors.
- Result: no new errors.

## Updated consolidated status
- Phase 0: Complete.
- Phase 1: Complete.
- Phase 2: Complete.
- Phase 3: Complete.

## Phase 4 - Partner + Multi-Org Doctrine Validation (Completed)

### Implemented changes
1. Multi-org auth resolver query hardening:
   - File: `includes/helpers.php`
   - Fixed `orabooks_resolve_auth_org_id()` membership lookup query to remove non-existent `id` column dependency.
   - Query now orders by `joined_at DESC` only, aligned with `user_org` schema.

2. Organization test coverage extended for resolver correctness:
   - File: `tests/OraBooks_Organization_Test.php`
   - Added test:
     - `test_resolve_auth_org_id_prefers_latest_membership_without_id_column_dependency`

3. Partner permission doctrine regression coverage extended:
   - File: `tests/OraBooks_RBAC_Test.php`
   - Added test:
     - `test_partner_commission_access_denied_for_staff_viewer_by_default`
   - Verifies staff/viewer remain denied unless partner org setting explicitly enables access.

4. Test bootstrap parity fix:
   - File: `tests/bootstrap.php`
   - Updated test stub `orabooks_resolve_auth_org_id()` to match production resolver query (removed `id DESC`).

### Validation after Phase 4
1. Organization suite:
- Command:
  - `php tests/vendor/bin/phpunit --configuration \\10.124.1.254\Jahid_ Shared_Folder\Project Share Folder\Lean MVP OraBooks\OraBooks Lean MVP\tests\phpunit.xml --testsuite "OraBooks Organization Tests"`
- Result:
  - `OK (12 tests, 21 assertions)`

2. RBAC suite:
- Command:
  - `php tests/vendor/bin/phpunit --configuration \\10.124.1.254\Jahid_ Shared_Folder\Project Share Folder\Lean MVP OraBooks\OraBooks Lean MVP\tests\phpunit.xml --testsuite "OraBooks RBAC Tests"`
- Result:
  - `OK (14 tests, 47 assertions)`

3. Auth regression suite:
- Command:
  - `php tests/vendor/bin/phpunit --configuration \\10.124.1.254\Jahid_ Shared_Folder\Project Share Folder\Lean MVP OraBooks\OraBooks Lean MVP\tests\phpunit.xml --testsuite "OraBooks Auth Tests"`
- Result:
  - `OK (100 tests, 303 assertions)`

4. Team regression suite:
- Command:
  - `php tests/vendor/bin/phpunit --configuration \\10.124.1.254\Jahid_ Shared_Folder\Project Share Folder\Lean MVP OraBooks\OraBooks Lean MVP\tests\phpunit.xml --testsuite "OraBooks Team Tests"`
- Result:
  - `OK (23 tests, 65 assertions)`

5. Diagnostics:
- Checked modified files for workspace errors.
- Result: no new errors.

## Latest consolidated status
- Phase 0: Complete.
- Phase 1: Complete.
- Phase 2: Complete.
- Phase 3: Complete.
- Phase 4: Complete.

## Phase 5 - Test Shield Expansion + Resend Endpoint Safety (Completed)

### Implemented changes
1. Resend invite endpoint safety hardening:
   - File: `includes/class-orabooks-team.php`
   - `ajax_resend_invite()` now validates pending invite existence (`used = 0`) and returns `404` when invite is missing/used.
   - Prevents null dereference paths and enforces deterministic error semantics.

2. Invite acceptance resilience tests added:
   - File: `tests/OraBooks_Team_Test.php`
   - Added:
     - `test_accept_invite_rejects_unverified_email`
     - `test_accept_invite_returns_already_member_flag_when_membership_exists`

3. Resend invite AJAX tests added:
   - File: `tests/OraBooks_Team_Test.php`
   - Added:
     - `test_ajax_resend_invite_rejects_missing_or_used_invite`
     - `test_ajax_resend_invite_rotates_token_and_logs_event`
   - Added JSON success capture helper for endpoint success assertions.

4. Team test setup permission parity fix:
   - File: `tests/OraBooks_Team_Test.php`
   - Added `OraBooks_RBAC::init()` in `setUp()` so AJAX permission checks use initialized permission matrix.

### Validation after Phase 5
1. Team suite:
- Command:
  - `php tests/vendor/bin/phpunit --configuration \\10.124.1.254\Jahid_ Shared_Folder\Project Share Folder\Lean MVP OraBooks\OraBooks Lean MVP\tests\phpunit.xml --testsuite "OraBooks Team Tests"`
- Result:
  - `OK (27 tests, 80 assertions)`

2. Auth regression suite:
- Command:
  - `php tests/vendor/bin/phpunit --configuration \\10.124.1.254\Jahid_ Shared_Folder\Project Share Folder\Lean MVP OraBooks\OraBooks Lean MVP\tests\phpunit.xml --testsuite "OraBooks Auth Tests"`
- Result:
  - `OK (100 tests, 303 assertions)`

3. RBAC regression suite:
- Command:
  - `php tests/vendor/bin/phpunit --configuration \\10.124.1.254\Jahid_ Shared_Folder\Project Share Folder\Lean MVP OraBooks\OraBooks Lean MVP\tests\phpunit.xml --testsuite "OraBooks RBAC Tests"`
- Result:
  - `OK (14 tests, 47 assertions)`

4. Organization regression suite:
- Command:
  - `php tests/vendor/bin/phpunit --configuration \\10.124.1.254\Jahid_ Shared_Folder\Project Share Folder\Lean MVP OraBooks\OraBooks Lean MVP\tests\phpunit.xml --testsuite "OraBooks Organization Tests"`
- Result:
  - `OK (12 tests, 21 assertions)`

## Final consolidated status
- Phase 0: Complete.
- Phase 1: Complete.
- Phase 2: Complete.
- Phase 3: Complete.
- Phase 4: Complete.
- Phase 5: Complete.

## Phase 6 - API/UI Contract and UX Hardening (Started)

### Implemented in kickoff
1. Frontend API error contract normalization:
   - File: `orabooks-ui/src/pages/frontend/api.ts`
   - `ApiResult` error branch now carries optional `status` and `code`.
   - Added error normalization helpers so JSON and HTTP failures preserve backend code and HTTP status for deterministic UI handling.

2. Team UI deterministic invite action handling:
   - File: `orabooks-ui/src/pages/frontend/pages/TeamPage.tsx`
   - Invite flow now provides status-aware messaging for conflict paths (already-member / `409`).
   - Resend and cancel flows now treat stale invite (`404`) as refresh-required state and auto-reload team data.
   - Resend success path now reloads dashboard data for UI/API state parity.

### Validation for kickoff patch
1. Frontend typecheck:
- Command:
  - `cmd /c "pushd \\10.124.1.254\Jahid_ Shared_Folder\Project Share Folder\Lean MVP OraBooks\OraBooks Lean MVP\orabooks-ui & npm run typecheck & popd"`
- Result:
  - `OK (tsc --noEmit passed; no TypeScript errors)`

## Updated status after Phase 6 kickoff
- Phase 0: Complete.
- Phase 1: Complete.
- Phase 2: Complete.
- Phase 3: Complete.
- Phase 4: Complete.
- Phase 5: Complete.
- Phase 6: In Progress (kickoff patch complete).

### Phase 6 - Next Part (Completed in this iteration)
1. Backend contract parity for cancel invite:
   - File: `includes/class-orabooks-team.php`
   - `ajax_cancel_invite()` now validates pending invite (`used = 0`) and returns `404` when invite is missing/used.
   - Aligns cancel semantics with resend semantics for deterministic API behavior.

2. Expanded test shield for cancel invite edge paths:
   - File: `tests/OraBooks_Team_Test.php`
   - Added:
     - `test_ajax_cancel_invite_rejects_missing_or_used_invite`
     - `test_ajax_cancel_invite_deletes_pending_invite_and_logs_event`

3. Validation rerun after next-part changes:
   - Team suite:
     - `OK (29 tests, 88 assertions)`
   - Auth suite:
     - `OK (100 tests, 303 assertions)`
   - RBAC suite:
     - `OK (14 tests, 47 assertions)`
   - Organization suite:
     - `OK (12 tests, 21 assertions)`
   - Frontend typecheck (`tsc --noEmit`):
     - Passed.

## Current status after Phase 6 next part
- Phase 0: Complete.
- Phase 1: Complete.
- Phase 2: Complete.
- Phase 3: Complete.
- Phase 4: Complete.
- Phase 5: Complete.
- Phase 6: In Progress (kickoff + contract parity + regression shield updates complete).
