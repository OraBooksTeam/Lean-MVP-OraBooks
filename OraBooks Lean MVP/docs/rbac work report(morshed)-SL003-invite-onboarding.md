# SL-003 RBAC — Invite-First Onboarding (Option A)

**Report addendum for:** `rbac work report(morshed).docx`  
**Date:** 2026-06-21  
**Spec alignment:** SL-003 RBAC / ABAC + SL-014 Team Invites  
**Approach:** Option A — Invite-first (no personal org for invited staff)

---

## 1. Problem Statement

When an owner invited a user as **staff**, the invited person registered as a **customer** and was sent through **tier selection**. That flow created a **new customer organization** and assigned the user **`owner`** role in their personal org. After accepting the invite, the sidebar badge and JWT context could show the wrong role (**Owner** instead of **Staff**).

This violated the SL-003 user journey:

> User invited with role X → Accept invite → Active with role X

**Important:** *Customer* (org type) is **not** the same as *Viewer* (RBAC role).

| Concept | Meaning |
|---------|---------|
| Customer org | `organizations.organization_type = 'customer'` |
| Viewer role | Read-only role in `user_org.role` |
| Owner role | Full control; only for org creators or explicit assignment |

---

## 2. Solution — Option A (Invite-First)

### Rules implemented

1. If a registered email has a **pending team invite**, **tier selection is skipped**.
2. On login (and 2FA/OIDC completion), the system **auto-accepts** the pending invite.
3. `user_org` row is created with the **invited role** (e.g. staff).
4. `users.org_id` is set to the **employer org** (not a personal org).
5. JWT is issued with `org_id` + `role` from the employer org context.
6. User is redirected to the **employer subdomain** (`/team/`).
7. **Tier selection API is blocked** (403) while a pending invite exists.

### SL-003 alignment

| SL-003 requirement | Status |
|--------------------|--------|
| Role from `user_org` in JWT | Done |
| Deny-by-default permission matrix | Unchanged |
| Role badge reflects current org role | Fixed via org context + badge sync |
| Permission cache invalidation on role change | Unchanged (refresh token revoke) |
| Partner org accounting block (SL-013) | Unchanged |
| Invite journey: role X → accept → role X | Done |

---

## 3. Files Changed

### Backend

| File | Change |
|------|--------|
| `includes/helpers.php` | `orabooks_get_pending_invite_for_email()`, `orabooks_user_has_any_pending_invite()`, enrich redirect for `needs_accept_invite` |
| `includes/class-orabooks-team.php` | Refactored `finalize_invite_acceptance()`, added `accept_pending_invite_for_user()` |
| `includes/class-orabooks-auth.php` | `try_invite_first_onboarding()` on login; block tier selection when invite pending; register response includes invite hint |
| `includes/class-orabooks-two-factor.php` | Session persist rules for invite onboarding |

### Frontend

| File | Change |
|------|--------|
| `orabooks-ui/src/pages/frontend/lib/auth-routing.ts` | Handle `needs_accept_invite` redirect |
| `orabooks-ui/src/pages/frontend/pages/RegisterPage.tsx` | Team invite banner; lock invited email; skip partner/customer choice when invited |
| `orabooks-ui/src/pages/frontend/pages/TierSelectionPage.tsx` | Redirect to accept-invite if tier API blocked |
| `orabooks-ui/src/pages/frontend/components/ClientShell.tsx` | Role badge sync (prior fix) |

### Tests

| File | Change |
|------|--------|
| `tests/OraBooks_RBAC_Test.php` | Tests for `accept_pending_invite_for_user()` and pending invite helper |

---

## 4. User Journey (After Fix)

```
Owner invites staff@company.com as Staff
        ↓
Staff opens invite link → Create account (invited email)
        ↓
Verify email → Log in
        ↓
Backend auto-accepts invite (no tier selection)
        ↓
JWT: org_id = owner org, role = staff
        ↓
Redirect to owner-subdomain/team/
        ↓
Sidebar badge: Role: Staff
Team page: member row shows Staff
Permissions: staff matrix (not owner)
```

---

## 5. Manual Test Plan (Live)

### Test A — Happy path (new invited staff)

1. As **Owner**, go to **Team** → invite `newstaff@test.com` as **Staff**.
2. Open invite email link in incognito (or separate browser).
3. Click **Create account** — confirm banner says team invitation (not “start your own company”).
4. Register with the **invited email**, verify email, log in.
5. **Expected:** No tier selection page. Redirect to owner subdomain `/team/`.
6. **Expected:** Sidebar badge **Role: Staff**. Team list shows you as Staff.
7. **Expected:** No access to owner-only actions (e.g. change roles).

### Test B — Tier selection blocked

1. With pending invite, try to open `/tier-selection/` manually after login attempt.
2. **Expected:** Either auto-redirect to team workspace or 403 on tier submit.

### Test C — Owner unchanged

1. Owner refreshes dashboard after staff joins.
2. **Expected:** Badge **Role: Owner**. Team page lists owner as Owner, new user as Staff.

### Test D — Non-invited signup (control)

1. Register a new email **without** invite.
2. **Expected:** Tier selection → create org → **Role: Owner** on own subdomain (correct).

---

## 6. PHPUnit

```bash
cd "OraBooks Lean MVP/tests"
vendor/bin/phpunit --configuration phpunit.xml OraBooks_RBAC_Test.php
```

**Expected:** 10 tests pass (includes invite-first onboarding tests).

---

## 7. Deploy Notes

1. Sync shared folder to live (PHP + `assets/react/frontend.js`).
2. Hard refresh browser (`Ctrl+Shift+R`).
3. Existing users who wrongly created a personal org before accepting: **log out → log in again** on invite email; auto-accept switches active org to employer.

---

## 8. Future (Out of Scope — Option B)

Multi-org support (one user, multiple companies) deferred to enterprise phase per SL-003 §10 Future Expansion.

---

*End of addendum — merge into main RBAC work report (Morshed).*
