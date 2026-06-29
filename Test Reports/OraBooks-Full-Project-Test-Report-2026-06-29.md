# OraBooks Lean MVP Full Project Test Report

Date: 2026-06-29

## 1. Executive Summary

This report covers the full OraBooks Lean MVP repository based on repository inspection, automated test inventory review, and a fresh backend test execution from the project test harness.

Overall outcome:

- Backend automated suite executed with PHPUnit 11.5.55 on PHP 8.5.7.
- Backend result: 689 tests, 2091 assertions, 7 failures, 9 warnings, 10 deprecations.
- Frontend automated suite is present but not wired to the npm test script; current `npm test` exits immediately with `Error: no test specified`.
- The strongest current regression concentration is in Customers / AR, followed by one Vendor workflow failure, one Tax snapshot mismatch, and frontend automation configuration drift.

## 2. Scope And Evidence Used

Scope reviewed:

- Product architecture and module map from `architecture.txt`.
- Admin feature surface from the `OraBooks Lean MVP/admin` pages.
- Backend automated tests from `OraBooks Lean MVP/tests`.
- Frontend automated tests from `OraBooks Lean MVP/tests/js`.
- Current backend execution from `OraBooks Lean MVP/tests/phpunit.xml`.

Evidence baseline:

- 41 backend PHPUnit test files were identified.
- 2 frontend Jest test files were identified: `frontend.test.js` and `admin.test.js`.
- Dedicated admin pages confirm live product areas including dashboard, audit, chart of accounts, CSV imports, customers, exports, queue, notifications, observability, organizations, partners, settings, and users.

## 3. Current Test Execution Status

### Backend Automation

Runner:

- PHPUnit 11.5.55
- PHP 8.5.7
- Config: `OraBooks Lean MVP/tests/phpunit.xml`

Execution result:

- Total tests: 689
- Assertions: 2091
- Failures: 7
- Warnings: 9
- Deprecations: 10
- Runtime: about 7.5 seconds

High-level interpretation:

- Most core modules are still passing.
- Current failures are not random; they cluster around invoice creation and payment-state handling in Customers, workflow handling in Vendors, and vendor bill tax snapshot normalization in Tax.
- Warnings indicate weak defensive defaults in AR wallet and Vendors object handling.

### Frontend Automation

Runner attempt:

- Command target: `OraBooks Lean MVP/tests/js`
- Result: `npm test` is still a placeholder script and exits with `Error: no test specified`

Interpretation:

- Frontend test files exist and appear meaningful.
- Current CI-style execution is blocked by package script configuration, so frontend status is partially covered by test source inspection but not by a fresh successful automated run.

## 4. Module And Feature Coverage Summary

| Module | Main Features Reviewed | Test Evidence | Current Result |
| --- | --- | --- | --- |
| Accounting Core | Chart of accounts, posting engine, double-entry enforcement, immutable ledger, idempotent posting | `OraBooks_COA_Test.php`, `OraBooks_Posting_Test.php` | Pass |
| Receivables / Customers | Customer management, invoice creation, duplicate control, payments, invoice lifecycle | `OraBooks_Customers_Test.php` | Fail |
| Payables / Vendors | Vendor records, bill posting, bill payment allocation, workflow updates | `OraBooks_Vendors_Test.php` | Fail |
| Expenses | Expense capture, OCR extraction, expense workflow | `OraBooks_Expenses_Test.php` | Pass |
| Inventory | Product stock, costing, movement logic | `OraBooks_Inventory_Test.php` | Pass |
| Workflow Engine | Workflow state machine and workflow integration | `OraBooks_Workflow_Test.php`, `OraBooks_Workflow_Integration_Test.php`, `OraBooks_Approval_Test.php` | Pass |
| AI Providers | Provider integration, extraction, confidence paths | `OraBooks_Ai_Providers_Test.php` | Pass |
| AI Review Queue | Review routing, queue state handling | `OraBooks_Ai_Review_Test.php` | Pass |
| Fiscal Governance | Fiscal open/close behavior, posting locks | `OraBooks_Fiscal_Test.php` | Pass |
| Tax | Tax governance, manual tax overrides, classification, vendor bill snapshots | `OraBooks_Tax_Test.php`, `OraBooks_Manual_Tax_Test.php`, `OraBooks_Classification_Test.php` | Fail |
| Financial Reports | P&L, balance sheet, report logic | `OraBooks_Financial_Reports_Test.php` | Pass |
| Operational Reports | Operational report generation | `OraBooks_Operational_Reports_Test.php` | Pass |
| Bank Reconciliation | Bank matching and reconciliation paths | `OraBooks_Bank_Reconciliation_Test.php` | Pass |
| Commission | Partner commission workflows and calculations | `OraBooks_Commission_Test.php` | Pass |
| CSV Imports | CSV import parsing and processing | `OraBooks_Csv_Imports_Test.php` | Pass |
| Exports | Export generation, export AJAX flows | `OraBooks_Exports_Test.php`, `OraBooks_Exports_Ajax_Test.php` | Pass |
| Authentication | Login, auth flow, user identity checks | `OraBooks_Auth_Test.php` | Pass |
| RBAC | Role-based access control and deploy checks | `OraBooks_RBAC_Test.php`, `OraBooks_Deploy_Checks_Test.php` | Pass |
| Two-Factor Security | MFA / 2FA flows | `OraBooks_TwoFactor_Test.php` | Pass |
| Secrets Management | Secret handling and rotation behavior | `OraBooks_Secrets_Test.php` | Pass |
| Security Hardening | Defensive security behavior and restrictions | `OraBooks_Security_Test.php` | Pass |
| REST API | API behavior and route handling | `OraBooks_Rest_Api_Test.php` | Pass |
| Notifications | Notification dispatch and notification center flows | `OraBooks_Notifications_Test.php` | Pass |
| Voice | Voice-related flow coverage | `OraBooks_Voice_Test.php` | Pass |
| Event Bus | Outbox and event bus semantics | `OraBooks_EventBus_Test.php` | Pass |
| Async Queue | Queue lifecycle, retries, dead-letter behavior | `OraBooks_AsyncQueue_Test.php` | Pass |
| Observability | SLI/SLO and observability reporting | `OraBooks_Observability_Test.php` | Pass |
| Organizations | Tenant / organization lifecycle | `OraBooks_Organization_Test.php` | Pass |
| Team | Team membership and role handling | `OraBooks_Team_Test.php` | Pass |
| Partners | Partner onboarding and management | `OraBooks_Partner_Test.php` | Pass |
| Platform Settings | Platform-level settings behavior | `OraBooks_Platform_Settings_Test.php` | Pass |
| Audit | Audit logging behavior | `OraBooks_Audit_Test.php` | Pass |
| Attachments | Attachment handling | `OraBooks_Attachments_Test.php` | Pass |
| PWA | Progressive web app surface | `OraBooks_Pwa_Test.php` | Pass |
| Frontend UI Automation | Registration, login, exports, notification center, admin UI actions | `tests/js/frontend.test.js`, `tests/js/admin.test.js` | Blocked |

## 5. Detailed Findings By Failed Or Blocked Area

### 5.1 Customers / Receivables

Status: Fail

Observed failures:

- Invoice creation does not always generate or expose an `idempotency_key` when missing.
- Duplicate invoice number handling returns `not_found` instead of the expected duplicate status.
- Payment recording does not consistently return or persist the expected new invoice status for full and partial payments.

Impact:

- Invoice integrity and duplicate prevention are central AR controls.
- Payment state regressions directly affect downstream aging, settlement visibility, and customer balance trustworthiness.

Warnings linked to this area:

- `class-orabooks-ar-wallet.php` reads `credit_hold` from a generic object without a safe default.
- Test assertions also exposed missing response keys such as `idempotency_key` and `new_status`.

### 5.2 Vendors / Payables

Status: Fail

Observed failures:

- A bill posting flow attempts workflow submission but receives `Only draft journals can be submitted`.

Impact:

- Vendor bill workflow state is misaligned with expected posting flow.
- This can block AP posting or hide a deeper workflow transition defect.

Warnings linked to this area:

- Missing defensive defaults for `cash_account_code`, `ap_account_code`, and `expense_account_code` on vendor-related objects.

### 5.3 Tax

Status: Fail

Observed failure:

- Vendor bill snapshot logic returns `vendor_bill` where the test expects canonical transaction type `bill`.

Impact:

- This is a normalization and compliance-consistency issue.
- Snapshot naming mismatches can leak into audit, reporting, or downstream policy logic.

### 5.4 Frontend Automation

Status: Blocked

Observed issue:

- Frontend Jest tests exist, but the package script is still the default placeholder and prevents normal automated execution.

Impact:

- UI and JavaScript regression coverage is not currently runnable from the standard npm entry point.
- Existing `jest-out.txt` also shows JSDOM navigation warnings, which suggests the UI harness may need cleanup even after the script is fixed.

## 6. Coverage Gaps And Residual Risk

The repository has broad automated coverage, but the following areas appear under-tested or only indirectly tested:

- Invoice document model direct tests are not obvious, even though invoice workflows are covered.
- Bill document model direct tests are not obvious, even though bill workflows are covered.
- Core loader / bootstrap-style classes appear to rely more on integration behavior than dedicated direct tests.
- View, shortcode, asset, and AJAX-dispatch infrastructure appear less directly covered than business logic modules.

Residual risk judgment:

- High residual risk in AR until invoice and payment regressions are fixed and retested.
- Medium residual risk in AP and Tax because failures are isolated but touch business-state integrity.
- Medium residual risk in frontend because automation is present but not currently executable through the standard test command.
- Low residual risk across passing infrastructure and governance modules, subject to the noted deprecations and warnings.

## 7. Recommended Retest Order

1. Customers / AR
2. Vendors / AP
3. Tax
4. Frontend npm test script and Jest harness
5. Full backend regression run

## 8. Issue Register Reference

The detailed issue register matching the requested column format is stored in:

- `Test Reports/OraBooks-Issue-Register-2026-06-29.csv`
